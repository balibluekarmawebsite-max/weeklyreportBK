<?php

namespace App\Services;

use App\Models\MonthlyStat;
use App\Models\ReportWeek;
use App\Support\ReportCalculator;
use Illuminate\Support\Collection;

/**
 * Assembles a report week's full data set (every section + computed totals) in
 * one place, so the Excel / Word / PDF exporters all render identical figures.
 */
class ReportData
{
    public function __construct(public ReportWeek $week)
    {
        $this->week->loadMissing([
            'property', 'owner', 'monthlyStats', 'segmentProductions', 'rateCodeProductions',
            'channelMonthRns', 'overviewBlocks', 'activities', 'socialMediaMetrics',
            'trainings', 'actionPlans', 'ownerRepeaterMonths', 'ownerChannelMix',
        ]);
    }

    public const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    public function property()
    {
        return $this->week->property;
    }

    public function monthlyStats(): Collection
    {
        return $this->week->monthlyStats->sortBy('month')->values();
    }

    public function monthlyTotals(): array
    {
        return ReportCalculator::monthlyTotals($this->monthlyStats());
    }

    /**
     * The headline month (1–12): the week's end-date month if it has figures,
     * else the latest month that does. Mirrors the dashboard so narrative and
     * KPIs agree. Null when no month has data.
     */
    public function currentMonth(): ?int
    {
        $stats = $this->monthlyStats()->keyBy('month');
        $hasData = fn ($s) => $s && ($s->rev_actual !== null || $s->occ_actual !== null);

        $end = (int) $this->week->end_date->format('n');
        if ($hasData($stats->get($end))) {
            return $end;
        }

        return $stats->filter($hasData)->keys()->last();
    }

    /** The MonthlyStat row for {@see currentMonth()}, or null. */
    public function currentStat(): ?MonthlyStat
    {
        $m = $this->currentMonth();

        return $m ? $this->monthlyStats()->firstWhere('month', $m) : null;
    }

    public function segments(): Collection
    {
        return $this->week->segmentProductions->sortBy('sort_order')->values();
    }

    public function segmentTotals(): array
    {
        return ReportCalculator::productionTotals($this->segments());
    }

    public function rateCodes(): Collection
    {
        return $this->week->rateCodeProductions->sortBy('sort_order')->values();
    }

    public function rateCodeTotals(): array
    {
        return ReportCalculator::productionTotals($this->rateCodes());
    }

    /** Channels grouped by year => collection of source rows. */
    public function channelsByYear(): Collection
    {
        return $this->week->channelMonthRns
            ->sortBy([['year', 'asc'], ['sort_order', 'asc']])
            ->groupBy('year');
    }

    public function overviewBlocks(): Collection
    {
        return $this->week->overviewBlocks->sortBy('sort_order')->values();
    }

    public function activities(string $department): Collection
    {
        return $this->week->activities->where('department', $department)->sortBy('sort_order')->values();
    }

    public function socialMetrics(): Collection
    {
        return $this->week->socialMediaMetrics->sortBy('sort_order')->values();
    }

    public function socialPlatform(): string
    {
        return $this->week->socialMediaMetrics->first()->platform ?? 'Instagram';
    }

    public function trainings(): Collection
    {
        return $this->week->trainings->sortBy('sort_order')->values();
    }

    /** Action plans grouped by category. */
    public function actionPlansByCategory(): Collection
    {
        return $this->week->actionPlans->sortBy('sort_order')->groupBy(fn ($p) => $p->category ?: 'General');
    }

    public function ownerRepeater(): Collection
    {
        return $this->week->ownerRepeaterMonths->sortBy('sort_order')->values();
    }

    public function ownerRepeaterTotals(): array
    {
        $rn = (int) $this->ownerRepeater()->sum('room_nights');
        $rev = (float) $this->ownerRepeater()->sum('revenue');

        return ['rn' => $rn, 'revenue' => $rev, 'adr' => ReportCalculator::rate($rev, $rn)];
    }

    public function ownerMix(): Collection
    {
        return $this->week->ownerChannelMix->sortBy('sort_order')->values();
    }

    public function ownerMixTotals(): array
    {
        return ReportCalculator::productionTotals($this->ownerMix());
    }

    /** A filesystem-safe base name for the export file. */
    public function filename(string $ext): string
    {
        $code = $this->property()?->code ?? 'report';
        $period = $this->week->label ? preg_replace('/[^A-Za-z0-9]+/', '_', $this->week->label) : $this->week->start_date->format('Y_m_d');

        return "{$code}_Weekly_Report_{$period}.{$ext}";
    }
}
