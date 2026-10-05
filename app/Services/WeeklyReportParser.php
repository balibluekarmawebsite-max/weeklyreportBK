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

        $data = [
            'meta' => $meta,
            'sectionB' => $this->sectionB($book),
            'sectionC' => $this->production($book, 'SM.2'),
            'sectionD' => $this->production($book, 'SM.3'),
            'channels' => $this->channels($book),
            'ownerRepeater' => $this->ownerRepeater($book),
            'ownerMix' => $this->ownerMix($book),
            'warnings' => [],
        ];

        $data['warnings'] = $this->warnings;

        return $data;
    }

    // ---- sections -------------------------------------------------------

    private function sectionB(Spreadsheet $book): array
    {
        $sheet = $this->sheet($book, 'SM.1');
        if (! $sheet) {
            return [];
        }

        $rows = [];
        for ($i = 0; $i < 12; $i++) {
            $r = 5 + $i; // Excel rows 5..16
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
        for ($r = 5; $r <= $highest; $r++) {
            $label = $this->str($sheet, "B{$r}");
            if ($label === '' || str_contains(strtolower($label), 'total')) {
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

            // Year marker: numeric year in C with empty B.
            if ($b === '' && $cNum !== null && $cNum > 1900 && $cNum < 2100) {
                $year = (int) $cNum;

                continue;
            }
            if ($year === null || $b === '' || in_array(strtolower($b), ['source', 'total'], true)) {
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

    private function ownerRepeater(Spreadsheet $book): array
    {
        $sheet = $this->sheet($book, 'OWNER OVERVIEW');
        if (! $sheet) {
            return [];
        }

        $out = [];
        for ($r = 6; $r <= 18; $r++) {
            $label = $this->str($sheet, "C{$r}");
            if ($label === '' || strtolower($label) === 'total') {
                continue;
            }
            $out[] = [
                'label' => $this->monthLabel($label),
                'room_nights' => $this->num($sheet, "D{$r}"),
                'revenue' => $this->num($sheet, "F{$r}"),
            ];
        }

        return $out;
    }

    private function ownerMix(Spreadsheet $book): array
    {
        $sheet = $this->sheet($book, 'OWNER OVERVIEW');
        if (! $sheet) {
            return [];
        }

        $out = [];
        for ($r = 30; $r <= 34; $r++) {
            $label = $this->str($sheet, "C{$r}");
            if ($label === '' || strtolower($label) === 'total') {
                continue;
            }
            $out[] = [
                'label' => $label,
                'rn_sold' => $this->num($sheet, "D{$r}"),
                'gross_revenue' => $this->num($sheet, "G{$r}"),
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
