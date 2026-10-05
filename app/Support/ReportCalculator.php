<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Pure calculation helpers for the weekly report sections.
 * Everything is null-safe and never produces error values — missing inputs
 * yield null, which the UI and exports render as "–".
 */
class ReportCalculator
{
    /** ARR / ADR = revenue / room nights. */
    public static function rate(int|float|null $revenue, int|float|null $roomNights): ?float
    {
        if (! $roomNights) {
            return null;
        }

        return (float) $revenue / $roomNights;
    }

    /** Share of a part within a total, as a percentage (0–100). */
    public static function sharePercent(int|float|null $part, int|float|null $total): ?float
    {
        if (! $total) {
            return null;
        }

        return ((float) $part / $total) * 100;
    }

    /**
     * Totals for a Section C / D / owner-mix style list of rows carrying
     * ->rn_sold and ->gross_revenue. Returns rn, revenue, and blended ARR.
     *
     * @param  Collection<int, object>|array<int, object>  $rows
     * @return array{rn:int, revenue:float, arr:?float}
     */
    public static function productionTotals(Collection|array $rows): array
    {
        $rows = collect($rows);
        $rn = (int) $rows->sum(fn ($r) => (int) ($r->rn_sold ?? 0));
        $revenue = (float) $rows->sum(fn ($r) => (float) ($r->gross_revenue ?? 0));

        return ['rn' => $rn, 'revenue' => $revenue, 'arr' => self::rate($revenue, $rn)];
    }

    /**
     * Totals for Section B monthly stats: summed room nights and revenue
     * (actual / budget / last year), with blended ARR for each.
     *
     * @param  Collection<int, object>|array<int, object>  $rows
     */
    public static function monthlyTotals(Collection|array $rows): array
    {
        $rows = collect($rows);
        $rn = (int) $rows->sum(fn ($r) => (int) ($r->rn_sold ?? 0));
        $revA = (float) $rows->sum(fn ($r) => (float) ($r->rev_actual ?? 0));
        $revB = (float) $rows->sum(fn ($r) => (float) ($r->rev_budget ?? 0));
        $revL = (float) $rows->sum(fn ($r) => (float) ($r->rev_ly ?? 0));

        return [
            'rn_sold' => $rn,
            'rev_actual' => $revA,
            'rev_budget' => $revB,
            'rev_ly' => $revL,
            'arr_actual' => self::rate($revA, $rn),
            'arr_budget' => self::rate($revB, $rn),
            'arr_ly' => self::rate($revL, $rn),
        ];
    }

    /**
     * Variance of actual vs a comparison value, returned as a signed
     * percentage (null when it cannot be computed).
     */
    public static function variancePercent(int|float|null $actual, int|float|null $compare): ?float
    {
        return Format::variance($actual, $compare)['pct'];
    }
}
