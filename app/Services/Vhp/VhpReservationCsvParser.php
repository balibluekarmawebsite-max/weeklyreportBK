<?php

namespace App\Services\Vhp;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Parses VHP's "Reservation By Creation Date" CSV export into Section C
 * (weekly production by market segment).
 *
 * Rules confirmed with the business:
 *  - group by VHP's own "Segment" column (FIT-OTA, OVS-TA, B2B, WEBS, FIT,
 *    COMPLIMENT) — mapped to friendly category labels, nothing overridden;
 *  - one row per agent ("Reservation Name") within its category;
 *  - exclude Cancelled reservations;
 *  - complimentary (COMPLIMENT segment) is kept as its own category.
 *
 * Room nights = Night × Room Quantity; revenue = Total Revenue. The file's own
 * header carries the property name and the period, so both are auto-detected.
 */
class VhpReservationCsvParser
{
    /** VHP Segment code → friendly category label (editable per-row after import). */
    public const SEGMENT_LABELS = [
        'FIT-OTA' => 'OTA',
        'OVS-TA' => 'Offline TA',
        'B2B' => 'B2B',
        'WEBS' => 'Website',
        'FIT' => 'Direct',
        'COMPLIMENT' => 'Compliment',
    ];

    /** Display order of categories (Compliment always last). */
    private const GROUP_ORDER = ['OTA', 'Offline TA', 'B2B', 'Website', 'Direct', 'Compliment'];

    /**
     * @return array{
     *   meta: array{property_name: ?string, period_label: ?string, start_date: ?string, end_date: ?string},
     *   segments: array<int, array{segment_group: string, label: string, rn_sold: int, gross_revenue: float}>,
     *   warnings: array<int, string>
     * }
     */
    public function parse(string $path): array
    {
        $rows = $this->readRows($path);

        $propertyName = $this->firstNonEmpty($rows[0] ?? []);
        [$periodLabel, $start, $end] = $this->findPeriod($rows);
        [$header, $headerIndex] = $this->findHeader($rows);

        if ($header === null) {
            throw new \RuntimeException('This does not look like a VHP "Reservation By Creation Date" export (no column header row found).');
        }

        $col = fn (array $row, string $name) => $headerIndex->has($name) ? ($row[$headerIndex->get($name)] ?? '') : '';

        // Aggregate by (segment group, agent).
        $acc = [];        // "group\0agent" => [group, agent, rn, revenue]
        $cancelled = 0;
        $unknownSegments = [];

        foreach ($rows as $i => $row) {
            if ($i <= $headerIndex->get('_row')) {
                continue; // metadata + header
            }
            if (! isset($row[0]) || ! ctype_digit(trim((string) $row[0]))) {
                continue; // not a data row
            }

            if (strtolower(trim((string) $col($row, 'Status'))) === 'cancelled') {
                $cancelled++;

                continue;
            }

            $segment = trim((string) $col($row, 'Segment'));
            $agent = $this->cleanAgent((string) $col($row, 'Reservation Name'));
            if ($agent === '') {
                continue;
            }

            $group = self::SEGMENT_LABELS[$segment] ?? $segment;
            if ($segment !== '' && ! isset(self::SEGMENT_LABELS[$segment]) && ! in_array($segment, $unknownSegments, true)) {
                $unknownSegments[] = $segment;
            }

            $rn = (int) round($this->num($col($row, 'Night')) * $this->num($col($row, 'Room Quantity')));
            $revenue = $this->num($col($row, 'Total Revenue'));

            $key = $group."\0".$agent;
            if (! isset($acc[$key])) {
                $acc[$key] = ['segment_group' => $group, 'label' => $agent, 'rn_sold' => 0, 'gross_revenue' => 0.0];
            }
            $acc[$key]['rn_sold'] += $rn;
            $acc[$key]['gross_revenue'] += $revenue;
        }

        $segments = $this->sorted(array_values($acc));

        $warnings = [];
        if ($cancelled > 0) {
            $warnings[] = "Excluded {$cancelled} cancelled reservation row(s).";
        }
        if ($unknownSegments) {
            $warnings[] = 'Unmapped VHP segment(s) kept as-is: '.implode(', ', $unknownSegments).'.';
        }

        return [
            'meta' => [
                'property_name' => $propertyName,
                'period_label' => $periodLabel,
                'start_date' => $start,
                'end_date' => $end,
            ],
            'segments' => $segments,
            'warnings' => $warnings,
        ];
    }

    /** @return array<int, array<int, string>> */
    private function readRows(string $path): array
    {
        $rows = [];
        if (($h = fopen($path, 'r')) !== false) {
            while (($data = fgetcsv($h, 0, ',', '"', '\\')) !== false) {
                $rows[] = $data;
            }
            fclose($h);
        }

        return $rows;
    }

    private function firstNonEmpty(array $row): ?string
    {
        foreach ($row as $cell) {
            $cell = ltrim((string) $cell, "\u{FEFF}"); // strip UTF-8 BOM
            if (trim($cell) !== '') {
                return trim($cell);
            }
        }

        return null;
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} [periodLabel, startDate, endDate] */
    private function findPeriod(array $rows): array
    {
        foreach (array_slice($rows, 0, 8) as $row) {
            foreach ($row as $cell) {
                if (stripos((string) $cell, 'Period') !== false && preg_match_all('/(\d{2})\/(\d{2})\/(\d{2,4})/', (string) $cell, $m, PREG_SET_ORDER)) {
                    $label = trim(preg_replace('/^.*Period\s*:?\s*/i', '', (string) $cell));
                    $start = $this->toDate($m[0]);
                    $end = isset($m[1]) ? $this->toDate($m[1]) : null;

                    return [$label ?: null, $start, $end];
                }
            }
        }

        return [null, null, null];
    }

    /** @param array{0:string,1:string,2:string} $m dd, mm, yy(yy) */
    private function toDate(array $m): ?string
    {
        try {
            $year = (int) $m[3];
            $year += $year < 100 ? 2000 : 0;

            return Carbon::create($year, (int) $m[2], (int) $m[1])->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{0: ?array<int,string>, 1: Collection<string,int>} */
    private function findHeader(array $rows): array
    {
        foreach ($rows as $i => $row) {
            $first = trim((string) ($row[0] ?? ''));
            if ($first === 'No' && in_array('Segment', array_map('trim', $row), true)) {
                $index = collect();
                foreach ($row as $pos => $name) {
                    $index->put(trim((string) $name), $pos);
                }
                $index->put('_row', $i);

                return [$row, $index];
            }
        }

        return [null, collect(['_row' => -1])];
    }

    private function cleanAgent(string $raw): string
    {
        $v = trim($raw);
        $v = rtrim($v, ", \t");
        $v = preg_replace('/\s+/', ' ', $v); // collapse internal double spaces

        return trim((string) $v);
    }

    private function num(string|int|float|null $s): float
    {
        $s = str_replace([','], '', trim((string) $s));

        return is_numeric($s) ? (float) $s : 0.0;
    }

    /**
     * Order by category (GROUP_ORDER, unknown groups after, Compliment last),
     * then by room nights descending within a category; assign sort_order.
     *
     * @param  array<int, array{segment_group:string,label:string,rn_sold:int,gross_revenue:float}>  $rows
     * @return array<int, array{segment_group:string,label:string,rn_sold:int,gross_revenue:float}>
     */
    private function sorted(array $rows): array
    {
        $rank = function (string $group): int {
            $i = array_search($group, self::GROUP_ORDER, true);

            return $i === false ? count(self::GROUP_ORDER) : $i;
        };

        usort($rows, function ($a, $b) use ($rank) {
            return [$rank($a['segment_group']), $a['segment_group'], -$a['rn_sold']]
                <=> [$rank($b['segment_group']), $b['segment_group'], -$b['rn_sold']];
        });

        return $rows;
    }
}
