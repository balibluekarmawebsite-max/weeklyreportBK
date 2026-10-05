<?php

namespace App\Services;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Parses a weekly-report (SM-format) .xlsx workbook into a structured array
 * matching the section tables, cleaning out #REF!/#DIV/0! and junk sheets.
 *
 * The same output shape is used by the importer and by the JSON seeder, so a
 * future raw-VHP parser can emit the same structure and reuse the importer.
 */
class WeeklyReportParser
{
    private array $warnings = [];

    public function parse(string $path, ?string $originalFilename = null): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);

        $meta = $this->meta($book, $originalFilename ?? basename($path));
        $reportYear = $meta['end_date'] ? (int) substr($meta['end_date'], 0, 4) : null;

        $data = [
            'meta' => $meta,
            'sectionB' => $this->sectionB($book, $reportYear),
            'sectionC' => $this->production($book, 'SM.2'),
            'sectionD' => $this->production($book, 'SM.3'),
            'channels' => $this->channels($book),
            'ownerRepeater' => $this->ownerRepeater($book),
            'ownerMix' => $this->ownerMix($book),
            // Phase 4 written sections
            'overview' => $this->overview($book),
            'sales' => $this->salesActivity($book),
            'ecommerce' => $this->ecommerce($book),
            'social' => $this->social($book),
            'trainings' => $this->trainings($book),
            'actionPlan' => $this->actionPlan($book),
            'warnings' => [],
        ];

        $data['warnings'] = $this->warnings;

        return $data;
    }

    // ---- sections -------------------------------------------------------

    private function sectionB(Spreadsheet $book, ?int $reportYear = null): array
    {
        $sheet = $this->sheet($book, 'SM.1');
        if (! $sheet) {
            return [];
        }

        // SM.1 stacks several "Year to Date" blocks (older years on top, the
        // current year below). Pick the block whose heading mentions the report
        // year; fall back to the last block. Never the stale one on top.
        $startRow = $this->sectionBStart($sheet, $reportYear);
        if ($startRow === null) {
            return [];
        }

        $rows = [];
        for ($i = 0; $i < 12; $i++) {
            $r = $startRow + $i;
            $rows[] = [
                'month' => $i + 1,
                'rn_sold' => $this->num($sheet, "C{$r}"),
                'occ_actual' => $this->num($sheet, "D{$r}"),
                'occ_budget' => $this->num($sheet, "E{$r}"),
                'occ_ly' => $this->num($sheet, "F{$r}"),
                'arr_actual' => $this->num($sheet, "G{$r}"),
                'arr_budget' => $this->num($sheet, "H{$r}"),
                'arr_ly' => $this->num($sheet, "I{$r}"),
                'rev_actual' => $this->num($sheet, "J{$r}"),
                'rev_budget' => $this->num($sheet, "K{$r}"),
                'rev_ly' => $this->num($sheet, "L{$r}"),
            ];
        }

        return $rows;
    }

    /**
     * Row number of "January" in the current YTD block of SM.1, or null.
     *
     * Each vertical YTD block is anchored by a header cell in column B that
     * starts with "Month" (e.g. "Month" or "Month - 2026"). The current block
     * is the one whose heading area mentions the report year; otherwise the
     * last block. Horizontal budget grids use "JANUARY"/"FEBRUARY" as column
     * headers (no "Month" cell) and are ignored.
     */
    private function sectionBStart($sheet, ?int $reportYear): ?int
    {
        $highest = $sheet->getHighestDataRow();

        // Collect every candidate block header (B starts with "Month").
        $headers = [];
        for ($r = 1; $r <= $highest; $r++) {
            if (str_starts_with(strtolower($this->str($sheet, "B{$r}")), 'month')) {
                $headers[] = $r;
            }
        }
        if (empty($headers)) {
            return null;
        }

        // Prefer the block whose header row + the 4 rows above mention the year.
        $chosen = null;
        if ($reportYear) {
            foreach ($headers as $h) {
                $context = $this->str($sheet, "B{$h}");
                for ($r = max(1, $h - 4); $r < $h; $r++) {
                    $context .= ' '.$this->str($sheet, "A{$r}").' '.$this->str($sheet, "B{$r}");
                }
                if (str_contains($context, (string) $reportYear)) {
                    $chosen = $h; // keep the last match
                }
            }
        }
        $chosen ??= end($headers);

        // The January data row sits 1–4 rows below the header (a sub-header may
        // intervene). Require a numeric RN value in column C to be safe.
        for ($r = $chosen + 1; $r <= $chosen + 4 && $r <= $highest; $r++) {
            if (strtolower($this->str($sheet, "B{$r}")) === 'january' && $this->num($sheet, "C{$r}") !== null) {
                return $r;
            }
        }

        return null;
    }

    /** Section C (SM.2) and D (SM.3): label=B, rn=C, gross=F. */
    private function production(Spreadsheet $book, string $sheetName): array
    {
        $sheet = $this->sheet($book, $sheetName);
        if (! $sheet) {
            return [];
        }

        $out = [];
        $highest = $sheet->getHighestDataRow();
        $dropped = 0;
        // Start at row 4 (after the title + "Source/Promotion" header). Header,
        // blank, subtotal and no-production rows are all skipped below.
        for ($r = 4; $r <= $highest; $r++) {
            $label = $this->str($sheet, "B{$r}");
            if ($label === '' || str_contains(strtolower($label), 'total')
                || in_array(strtolower($label), ['source', 'promotion'], true)) {
                continue;
            }
            $rn = $this->num($sheet, "C{$r}");
            if ($rn === null) {
                $dropped++;
                continue; // no production (was #DIV/0! in the sheet)
            }
            $out[] = ['label' => $label, 'rn_sold' => (int) $rn, 'gross_revenue' => $this->num($sheet, "F{$r}")];
        }
        if ($dropped > 0) {
            $this->warnings[] = "Section {$sheetName}: skipped {$dropped} empty/error line(s).";
        }

        return $out;
    }

    private function channels(Spreadsheet $book): array
    {
        $sheet = $this->sheet($book, 'SM.4');
        if (! $sheet) {
            return [];
        }

        $out = [];
        $year = null;
        $highest = $sheet->getHighestDataRow();
        for ($r = 1; $r <= $highest; $r++) {
            $b = $this->str($sheet, "B{$r}");
            $cNum = $this->num($sheet, "C{$r}");

            // Year marker: a year value in column C. Column B is usually empty
            // but some files leave a stray number there (e.g. "2"), so we accept
            // the marker as long as B is not a real (alphabetic) source name.
            if ($cNum !== null && $cNum >= 2018 && $cNum <= 2100 && ($b === '' || is_numeric($b))) {
                $year = (int) $cNum;

                continue;
            }
            if ($year === null || $b === '' || is_numeric($b)
                || in_array(strtolower($b), ['source', 'total'], true)) {
                continue;
            }
            // Month room nights in cols C..N.
            $months = [];
            foreach (['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'] as $col) {
                $months[] = (int) ($this->num($sheet, "{$col}{$r}") ?? 0);
            }
            $out[] = ['year' => $year, 'source' => $b, 'months' => $months];
        }

        return $out;
    }

    /**
     * Owner Overview repeater table. Row positions differ per property, so
     * anchor on the "Month" / "Total Room Nights" header and read until TOTAL.
     * Month labels can be text or Excel date serials.
     */
    private function ownerRepeater(Spreadsheet $book): array
    {
        $sheet = $this->sheet($book, 'OWNER OVERVIEW');
        if (! $sheet) {
            return [];
        }

        $header = $this->ownerHeaderRow($sheet, 'month', 'room night');
        if ($header === null) {
            return [];
        }

        $out = [];
        $highest = $sheet->getHighestDataRow();
        for ($r = $header + 1; $r <= min($header + 20, $highest); $r++) {
            $label = $this->str($sheet, "C{$r}");
            if ($label === '') {
                continue;
            }
            if (strtolower($label) === 'total' || str_starts_with(strtolower($label), 'total')) {
                break;
            }
            $out[] = [
                'label' => $this->ownerMonthLabel($sheet, "C{$r}"),
                'room_nights' => $this->num($sheet, "D{$r}"),
                'revenue' => $this->num($sheet, "F{$r}"),
            ];
        }

        return $out;
    }

    /**
     * Owner Overview channel-mix table. Anchor on the "Source" / "Room Nights
     * Sold" header and read until Total.
     */
    private function ownerMix(Spreadsheet $book): array
    {
        $sheet = $this->sheet($book, 'OWNER OVERVIEW');
        if (! $sheet) {
            return [];
        }

        $header = $this->ownerHeaderRow($sheet, 'source', 'room night');
        if ($header === null) {
            return [];
        }

        $out = [];
        $highest = $sheet->getHighestDataRow();
        for ($r = $header + 1; $r <= min($header + 12, $highest); $r++) {
            $label = $this->str($sheet, "C{$r}");
            if ($label === '') {
                continue;
            }
            if (str_starts_with(strtolower($label), 'total')) {
                break;
            }
            $out[] = [
                'label' => $label,
                'rn_sold' => $this->num($sheet, "D{$r}"),
                'gross_revenue' => $this->num($sheet, "G{$r}"),
            ];
        }

        return $out;
    }

    /** Find a header row in Owner Overview where col C == $cLabel and col D contains $dContains. */
    private function ownerHeaderRow($sheet, string $cLabel, string $dContains): ?int
    {
        $highest = $sheet->getHighestDataRow();
        for ($r = 1; $r <= $highest; $r++) {
            if (strtolower($this->str($sheet, "C{$r}")) === $cLabel
                && str_contains(strtolower($this->str($sheet, "D{$r}")), $dContains)) {
                return $r;
            }
        }

        return null;
    }

    /** Month label from a cell that may hold text or an Excel date serial. */
    private function ownerMonthLabel($sheet, string $coord): string
    {
        $v = $this->raw($sheet, $coord);
        if (is_numeric($v) && $v > 20000 && $v < 80000) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v)->format('F Y');
            } catch (\Throwable) {
                // fall through
            }
        }
        $s = $this->str($sheet, $coord);
        // Normalise "2026-02-01 00:00:00" style text to "February 2026".
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
            try {
                return \Carbon\Carbon::parse($s)->format('F Y');
            } catch (\Throwable) {
            }
        }

        return $s;
    }

    // ---- Phase 4 written sections ---------------------------------------

    private function overview(Spreadsheet $book): array
    {
        $sheet = $this->sheet($book, 'SM');
        if (! $sheet) {
            return [];
        }

        return [
            'financial' => $this->str($sheet, 'C7'),
            'market' => $this->str($sheet, 'C11'),
        ];
    }

    /** G — Sales Activity (SM.5). Entries start on a dated row; notes span rows. */
    private function salesActivity(Spreadsheet $book): array
    {
        $sheet = $this->sheet($book, 'SM.5');
        if (! $sheet) {
            return [];
        }

        $out = [];
        $cur = null;
        $highest = $sheet->getHighestDataRow();
        for ($r = 4; $r <= $highest; $r++) {
            $b = $this->str($sheet, "B{$r}");
            $c = $this->str($sheet, "C{$r}");
            $d = $this->str($sheet, "D{$r}");
            if ($b !== '') {
                if ($cur) {
                    $out[] = $cur;
                }
                $cur = ['date' => $this->dateCell($sheet, "B{$r}"), 'subject' => $c, 'notes' => $d];
            } elseif ($cur && $d !== '') {
                $cur['notes'] = trim($cur['notes']."\n".$d);
            }
        }
        if ($cur) {
            $out[] = $cur;
        }

        return $out;
    }

    /** G2 — E-commerce (SM.6). One row per entry. */
    private function ecommerce(Spreadsheet $book): array
    {
        $sheet = $this->sheet($book, 'SM.6');
        if (! $sheet) {
            return [];
        }

        $out = [];
        $highest = $sheet->getHighestDataRow();
        for ($r = 6; $r <= $highest; $r++) {
            $b = $this->str($sheet, "B{$r}");
            $c = $this->str($sheet, "C{$r}");
            if ($b === '' && $c === '') {
                continue;
            }
            if (strtolower($b) === 'date') {
                continue;
            }
            $out[] = ['date' => $this->dateCell($sheet, "B{$r}"), 'task' => $c, 'remarks' => $this->str($sheet, "D{$r}")];
        }

        return $out;
    }

    /** H — Social Media (SM.7). Row 5 = last week, row 6 = this week. */
    private function social(Spreadsheet $book): array
    {
        $sheet = $this->sheet($book, 'SM.7');
        if (! $sheet) {
            return [];
        }

        $metrics = ['website_visit', 'profile_visit', 'account_reached', 'impression', 'followers'];
        $cols = ['C', 'D', 'E', 'F', 'G'];
        $out = [];
        foreach ($metrics as $i => $m) {
            $out[] = [
                'metric' => $m,
                'last_week' => $this->num($sheet, $cols[$i].'5'),
                'this_week' => $this->num($sheet, $cols[$i].'6'),
            ];
        }

        return $out;
    }

    /** I — Training (SM.8). */
    private function trainings(Spreadsheet $book): array
    {
        $sheet = $this->sheet($book, 'SM.8');
        if (! $sheet) {
            return [];
        }

        $out = [];
        $highest = $sheet->getHighestDataRow();
        for ($r = 5; $r <= $highest; $r++) {
            $topic = $this->str($sheet, "D{$r}");
            if ($topic === '' || strtolower($topic) === 'training topic') {
                continue;
            }
            $out[] = [
                'date' => $this->dateCell($sheet, "C{$r}"),
                'topic' => $topic,
                'duration' => $this->str($sheet, "E{$r}"),
                'trainer' => $this->str($sheet, "F{$r}"),
                'participants' => $this->str($sheet, "G{$r}"),
            ];
        }

        return $out;
    }

    /** J — Next Week Action Plan (SM.9), grouped by category. */
    private function actionPlan(Spreadsheet $book): array
    {
        $sheet = $this->sheet($book, 'SM.9');
        if (! $sheet) {
            return [];
        }

        $out = [];
        $cat = null;
        $highest = $sheet->getHighestDataRow();
        for ($r = 6; $r <= $highest; $r++) {
            $b = $this->str($sheet, "B{$r}");
            $c = $this->str($sheet, "C{$r}");
            if ($b !== '' && $c === '') {
                $cat = $b; // category header
                continue;
            }
            if ($c === '' || strtolower($b) === 'no') {
                continue;
            }
            $out[] = [
                'category' => $cat ?? '',
                'plan' => $c,
                'start' => $this->dateCell($sheet, "D{$r}"),
                'deadline' => $this->dateCell($sheet, "E{$r}"),
                'remark' => $this->str($sheet, "F{$r}"),
            ];
        }

        return $out;
    }

    // ---- meta -----------------------------------------------------------

    private function meta(Spreadsheet $book, string $filename): array
    {
        // Property code from the filename prefix (e.g. "BKDS_Weekly_Report...").
        $code = null;
        if (preg_match('/^([A-Za-z]{2,6})[ _-]/', $filename, $m)) {
            $code = strtoupper($m[1]);
        }

        // Period from the filename, e.g. "25_Sep_-_1_Oct_2026".
        [$start, $end, $label] = $this->parsePeriod($filename);

        return [
            'property_code' => $code,
            'start_date' => $start?->toDateString(),
            'end_date' => $end?->toDateString(),
            'period_label' => $label,
            'source_filename' => $filename,
        ];
    }

    private function parsePeriod(string $filename): array
    {
        // Matches "25_Sep_-_1_Oct_2026" or "25 Sep - 1 Oct 2026".
        if (preg_match('/(\d{1,2})[ _]([A-Za-z]{3,9})[ _]*-[ _]*(\d{1,2})[ _]([A-Za-z]{3,9})[ _](\d{4})/', $filename, $m)) {
            $year = (int) $m[5];
            try {
                $end = Carbon::parse("{$m[3]} {$m[4]} {$year}");
                $startYear = $year;
                // If start month is later than end month, the period crosses a year.
                $startProbe = Carbon::parse("{$m[1]} {$m[2]} {$year}");
                if ($startProbe->greaterThan($end)) {
                    $startYear = $year - 1;
                }
                $start = Carbon::parse("{$m[1]} {$m[2]} {$startYear}");

                return [$start, $end, $start->format('d M').' – '.$end->format('d M Y')];
            } catch (\Throwable) {
                // fall through
            }
        }

        return [null, null, null];
    }

    private function monthLabel(string $label): string
    {
        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $label)) {
                return Carbon::parse($label)->format('F Y');
            }
        } catch (\Throwable) {
            // keep original
        }

        return $label;
    }

    // ---- cell helpers ---------------------------------------------------

    private function sheet(Spreadsheet $book, string $name)
    {
        $sheet = $book->getSheetByName($name);
        if (! $sheet) {
            $this->warnings[] = "Sheet '{$name}' not found.";
        }

        return $sheet;
    }

    /** Numeric value or null (strings like "#DIV/0!" become null). */
    private function num($sheet, string $coord): int|float|null
    {
        $v = $this->raw($sheet, $coord);
        if (is_int($v) || is_float($v)) {
            return $v;
        }
        if (is_string($v)) {
            $s = str_replace([',', ' '], '', trim($v));
            if ($s !== '' && is_numeric($s)) {
                return 0 + $s;
            }
        }

        return null;
    }

    private function str($sheet, string $coord): string
    {
        $v = $this->raw($sheet, $coord);

        return $v === null ? '' : trim((string) $v);
    }

    /**
     * A date-bearing cell. Excel stores dates as serial numbers (and
     * data-only reads skip number formats), so convert a plausible serial to a
     * "d M Y" string; otherwise return the text as-is.
     */
    private function dateCell($sheet, string $coord): string
    {
        $v = $this->raw($sheet, $coord);
        if (is_numeric($v) && $v > 20000 && $v < 80000) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v)->format('d M Y');
            } catch (\Throwable) {
                // fall through
            }
        }

        return $v === null ? '' : trim((string) $v);
    }

    /** Raw cell value, preferring the cached result for formula cells. */
    private function raw($sheet, string $coord)
    {
        $cell = $sheet->getCell($coord);
        $v = $cell->getValue();

        if (is_string($v) && str_starts_with($v, '=')) {
            // Formula cell — use the cached result, never recalculated
            // (recalculation would re-introduce #DIV/0! errors).
            $cached = $cell->getOldCalculatedValue();

            return $cached;
        }

        return $v;
    }
}
