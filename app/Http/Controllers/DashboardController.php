<?php

namespace App\Http\Controllers;

use App\Support\Format;
use App\Support\ReportCalculator;
use App\Support\Workspace;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    private const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    public function index(): View
    {
        $property = Workspace::currentProperty();

        $week = $property
            ? $property->reportWeeks()->latest('start_date')->first()
            : null;

        $recentWeeks = $property
            ? $property->reportWeeks()->latest('start_date')->take(5)->get()
            : collect();

        $stats = $week ? $week->monthlyStats()->orderBy('month')->get()->keyBy('month') : collect();

        // The report is "as of" its end date, so the headline month is the
        // end-date month (e.g. week 25 Sep – 1 Oct → October). Fall back to the
        // latest month that actually has figures.
        $currentMonth = null;
        if ($week) {
            $m = (int) $week->end_date->format('n');
            if (optional($stats->get($m))->rev_actual !== null || optional($stats->get($m))->occ_actual !== null) {
                $currentMonth = $m;
            } else {
                $currentMonth = $stats->filter(fn ($s) => $s->rev_actual !== null || $s->occ_actual !== null)->keys()->last();
            }
        }
        $cur = $currentMonth ? $stats->get($currentMonth) : null;

        return view('dashboard', [
            'property' => $property,
            'week' => $week,
            'recentWeeks' => $recentWeeks,
            'currentMonthLabel' => $currentMonth ? self::MONTH_NAMES[$currentMonth - 1] : null,
            'kpis' => $this->kpis($cur),
            'charts' => $this->charts($week, $stats),
            'progress' => $this->progress($week),
        ]);
    }

    private function kpis(?object $s): array
    {
        if (! $s) {
            return [];
        }

        return [
            'occupancy' => [
                'value' => $s->occ_actual !== null ? Format::percent($s->occ_actual * 100) : '–',
                'var' => Format::variance($s->occ_actual, $s->occ_budget),
            ],
            'adr' => [
                'value' => Format::idr($s->arr_actual),
                'var' => Format::variance($s->arr_actual, $s->arr_budget),
            ],
            'revenue' => [
                'value' => Format::idr($s->rev_actual),
                'var' => Format::variance($s->rev_actual, $s->rev_budget),
            ],
            'rn' => [
                'value' => Format::number($s->rn_sold),
                'var' => ['pct' => null, 'direction' => 'flat'],
            ],
        ];
    }

    private function charts(?object $week, $stats): array
    {
        $occ = ['actual' => [], 'budget' => [], 'ly' => []];
        $rev = ['actual' => [], 'budget' => [], 'ly' => []];
        for ($m = 1; $m <= 12; $m++) {
            $s = $stats->get($m);
            $occ['actual'][] = $s && $s->occ_actual !== null ? round($s->occ_actual * 100, 2) : null;
            $occ['budget'][] = $s && $s->occ_budget !== null ? round($s->occ_budget * 100, 2) : null;
            $occ['ly'][] = $s && $s->occ_ly !== null ? round($s->occ_ly * 100, 2) : null;
            $rev['actual'][] = $s?->rev_actual;
            $rev['budget'][] = $s?->rev_budget;
            $rev['ly'][] = $s?->rev_ly;
        }

        // Channel mix for the week (Section C segments), ranked, top 7 + Other.
        $segments = $week ? $week->segmentProductions()->get() : collect();
        $totalRn = (int) $segments->sum('rn_sold');
        $ranked = $segments->filter(fn ($s) => (int) $s->rn_sold > 0)
            ->sortByDesc('rn_sold')->values();
        $top = $ranked->take(7);
        $otherRn = (int) $ranked->slice(7)->sum('rn_sold');
        $mix = ['labels' => [], 'rn' => [], 'pct' => []];
        foreach ($top as $s) {
            $mix['labels'][] = $s->label;
            $mix['rn'][] = (int) $s->rn_sold;
            $mix['pct'][] = ReportCalculator::sharePercent($s->rn_sold, $totalRn);
        }
        if ($otherRn > 0) {
            $mix['labels'][] = 'Other';
            $mix['rn'][] = $otherRn;
            $mix['pct'][] = ReportCalculator::sharePercent($otherRn, $totalRn);
        }

        return [
            'months' => self::MONTHS,
            'occupancy' => $occ,
            'revenue' => $rev,
            'channelMix' => $mix,
            'hasData' => $stats->isNotEmpty(),
        ];
    }

    private function progress(?object $week): array
    {
        if (! $week) {
            return ['sections' => [], 'done' => 0, 'total' => 0];
        }

        $sections = [
            'A — Overview' => $week->overviewBlocks()->whereNotNull('body')->where('body', '!=', '')->exists(),
            'B — YTD Actual & Forecast' => $week->monthlyStats()->exists(),
            'C — Market Segment' => $week->segmentProductions()->exists(),
            'D — Rate Code' => $week->rateCodeProductions()->exists(),
            'E/F — Channels' => $week->channelMonthRns()->exists(),
            'G — Sales Activity' => $week->activities()->where('department', 'sales')->exists(),
            'G2 — E-commerce' => $week->activities()->where('department', 'ecommerce')->exists(),
            'H — Social Media' => $week->socialMediaMetrics()->whereNotNull('this_week')->exists(),
            'I — Training' => $week->trainings()->exists(),
            'J — Action Plan' => $week->actionPlans()->exists(),
            'Owner Overview' => $week->ownerRepeaterMonths()->exists(),
        ];

        return [
            'sections' => $sections,
            'done' => count(array_filter($sections)),
            'total' => count($sections),
        ];
    }
}
