<?php

namespace App\Support;

use App\Services\ReportData;

/**
 * Deterministic anomaly scan for a report week — big variances vs budget/last
 * year, impossible figures, and missing sections. This is pure calculation (no
 * AI, no network), so "check for anomalies" is reliable and works even when the
 * AI key is absent. The AI layer may optionally narrate these findings.
 */
class ReportInspector
{
    /** Variance thresholds (percentage points / percent) that count as notable. */
    private const OCC_VAR = 10.0;   // occupancy: ±10 pts vs budget

    private const ADR_VAR = 20.0;   // ADR: ±20% vs budget

    private const REV_VAR = 20.0;   // revenue: ±20% vs budget

    /**
     * @return array<int, string> Human-readable findings; empty when all clear.
     */
    public static function anomalies(ReportData $data): array
    {
        $out = [];
        $stat = $data->currentStat();
        $month = $data->currentMonth();
        $monthName = $month ? ReportData::MONTHS[$month - 1] : null;

        if ($stat && $monthName) {
            // Occupancy is a 0..1 fraction; compare in percentage points.
            if ($stat->occ_actual !== null && $stat->occ_budget !== null) {
                $pts = ($stat->occ_actual - $stat->occ_budget) * 100;
                if (abs($pts) >= self::OCC_VAR) {
                    $out[] = sprintf(
                        '%s occupancy is %.1f pts %s budget (%s vs %s).',
                        $monthName, abs($pts), $pts < 0 ? 'below' : 'above',
                        Format::percent($stat->occ_actual * 100), Format::percent($stat->occ_budget * 100),
                    );
                }
            }
            if ($stat->occ_actual !== null && ($stat->occ_actual > 1.0 || $stat->occ_actual < 0)) {
                $out[] = sprintf('%s occupancy is outside 0–100%% (%s) — check the import.', $monthName, Format::percent($stat->occ_actual * 100));
            }

            $out = array_merge($out, self::pctFinding($monthName, 'ADR', $stat->arr_actual, $stat->arr_budget, self::ADR_VAR, true));
            $out = array_merge($out, self::pctFinding($monthName, 'room revenue', $stat->rev_actual, $stat->rev_budget, self::REV_VAR, true));
        }

        foreach (self::missingSections($data) as $m) {
            $out[] = $m;
        }

        return $out;
    }

    /** @return array<int, string> */
    private static function pctFinding(string $month, string $label, int|float|null $actual, int|float|null $budget, float $threshold, bool $money): array
    {
        $var = Format::variance($actual, $budget);
        if ($var['pct'] === null || abs($var['pct']) < $threshold) {
            return [];
        }

        return [sprintf(
            '%s %s is %.1f%% %s budget (%s vs %s).',
            $month, $label, abs($var['pct']), $var['pct'] < 0 ? 'below' : 'above',
            $money ? Format::idrPrefixed($actual) : Format::number($actual),
            $money ? Format::idrPrefixed($budget) : Format::number($budget),
        )];
    }

    /** @return array<int, string> */
    private static function missingSections(ReportData $data): array
    {
        $out = [];
        $checks = [
            'Section B (year-to-date figures)' => $data->monthlyStats()->isNotEmpty(),
            'Section C (market segment production)' => $data->segments()->isNotEmpty(),
            'Section A commentary' => $data->overviewBlocks()->contains(fn ($b) => filled($b->body)),
            'Section J (next-week action plan)' => $data->week->actionPlans()->exists(),
        ];
        foreach ($checks as $label => $present) {
            if (! $present) {
                $out[] = $label.' has no data yet.';
            }
        }

        return $out;
    }
}
