<?php

namespace App\Support;

use App\Models\MonthlyStat;
use App\Models\Property;
use App\Models\ReportWeek;
use Illuminate\Support\Collection;

/**
 * Week-over-week trends, comparison and a cross-property portfolio snapshot.
 *
 * Each week's "headline" figures come from the monthly_stats row for the
 * week's end-date month (the same rule the dashboard uses), so trends line up
 * with the KPIs shown there. Occupancy is expressed as a percent (0–100).
 */
class TrendService
{
    /** The monthly stat that represents a week's headline figures, or null. */
    public static function headlineStat(ReportWeek $week): ?MonthlyStat
    {
        $stats = $week->monthlyStats()->orderBy('month')->get()->keyBy('month');
        if ($stats->isEmpty()) {
            return null;
        }

        $hasFigures = fn (?MonthlyStat $s) => $s && ($s->rev_actual !== null || $s->occ_actual !== null);

        $endMonth = (int) $week->end_date->format('n');
        if ($hasFigures($stats->get($endMonth))) {
            return $stats->get($endMonth);
        }

        // Fall back to the latest month that actually carries figures.
        $key = $stats->filter($hasFigures)->keys()->last();

        return $key ? $stats->get($key) : null;
    }

    /**
     * Headline KPIs for one week.
     *
     * @return array{occ: float|null, adr: float|null, rev: float|null, rn: int|null}
     */
    public static function weekKpis(ReportWeek $week): array
    {
        $s = self::headlineStat($week);

        return [
            'occ' => $s && $s->occ_actual !== null ? round($s->occ_actual * 100, 1) : null,
            'adr' => $s?->arr_actual,
            'rev' => $s?->rev_actual,
            'rn' => $s && $s->rn_sold !== null ? (int) $s->rn_sold : null,
        ];
    }

    /**
     * The last N weeks for a property, oldest first, each with its KPIs.
     *
     * @return Collection<int, array{week: ReportWeek, label: string, short: string, kpis: array}>
     */
    public static function series(Property $property, int $limit = 8): Collection
    {
        return $property->reportWeeks()
            ->latest('start_date')->take($limit)->get()
            ->sortBy('start_date')->values()
            ->map(fn (ReportWeek $w) => [
                'week' => $w,
                'label' => $w->label ?? $w->start_date->format('d M Y'),
                'short' => $w->start_date->format('d M'),
                'kpis' => self::weekKpis($w),
            ]);
    }

    /**
     * The latest week versus the week before it, with per-metric deltas.
     * Returns null when the property has no weeks.
     */
    public static function comparison(Property $property): ?array
    {
        $weeks = $property->reportWeeks()->latest('start_date')->take(2)->get();
        if ($weeks->isEmpty()) {
            return null;
        }

        $current = $weeks->get(0);
        $previous = $weeks->get(1);
        $cur = self::weekKpis($current);
        $prev = $previous ? self::weekKpis($previous) : null;

        $metrics = [];
        foreach (['occ', 'adr', 'rev', 'rn'] as $k) {
            $metrics[$k] = self::delta($cur[$k] ?? null, $prev[$k] ?? null);
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'cur' => $cur,
            'prev' => $prev,
            'metrics' => $metrics,
        ];
    }

    /**
     * Change from the previous value to the current one.
     *
     * @return array{abs: float|null, pct: float|null, direction: string}
     */
    private static function delta(int|float|null $cur, int|float|null $prev): array
    {
        if ($cur === null || $prev === null) {
            return ['abs' => null, 'pct' => null, 'direction' => 'flat'];
        }

        $abs = $cur - $prev;
        $pct = $prev != 0.0 ? round(($abs / abs($prev)) * 100, 1) : null;
        $direction = $abs > 0 ? 'up' : ($abs < 0 ? 'down' : 'flat');

        return ['abs' => $abs, 'pct' => $pct, 'direction' => $direction];
    }

    /**
     * Latest-week KPIs for every active property — the portfolio snapshot.
     *
     * @return Collection<int, array{property: Property, week: ReportWeek|null, kpis: array}>
     */
    public static function portfolio(): Collection
    {
        return Property::where('is_active', true)->orderBy('code')->get()
            ->map(function (Property $p) {
                $week = $p->reportWeeks()->latest('start_date')->first();

                return [
                    'property' => $p,
                    'week' => $week,
                    'kpis' => $week
                        ? self::weekKpis($week)
                        : ['occ' => null, 'adr' => null, 'rev' => null, 'rn' => null],
                ];
            });
    }
}
