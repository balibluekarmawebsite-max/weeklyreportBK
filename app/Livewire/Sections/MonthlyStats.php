<?php

namespace App\Livewire\Sections;

use App\Models\ActivityLog;
use App\Models\MonthlyStat;
use App\Models\ReportWeek;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Section B — Year to Date Actual & On-Hand Forecast.
 * Editable grid of 12 months. Occupancy is edited as a percentage and stored
 * as a fraction (0–1). ARR/variance/totals are computed live.
 */
class MonthlyStats extends Component
{
    public ReportWeek $week;

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public bool $saved = false;

    private const MONTHS = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    public function mount(ReportWeek $week): void
    {
        $this->week = $week;
        $existing = $week->monthlyStats()->get()->keyBy('month');

        foreach (self::MONTHS as $m => $name) {
            $r = $existing->get($m);
            $this->rows[$m] = [
                'month' => $m,
                'name' => $name,
                'rn_sold' => $r?->rn_sold,
                // occupancy fraction -> percent for editing
                'occ_actual' => self::toPercent($r?->occ_actual),
                'occ_budget' => self::toPercent($r?->occ_budget),
                'occ_ly' => self::toPercent($r?->occ_ly),
                'arr_actual' => $r?->arr_actual,
                'arr_budget' => $r?->arr_budget,
                'arr_ly' => $r?->arr_ly,
                'rev_actual' => $r?->rev_actual,
                'rev_budget' => $r?->rev_budget,
                'rev_ly' => $r?->rev_ly,
            ];
        }
    }

    public function save(): void
    {
        if ($this->week->isLocked()) {
            return;
        }

        foreach ($this->rows as $m => $row) {
            MonthlyStat::updateOrCreate(
                ['report_week_id' => $this->week->id, 'month' => $m],
                [
                    'rn_sold' => self::n($row['rn_sold']),
                    'occ_actual' => self::fromPercent($row['occ_actual']),
                    'occ_budget' => self::fromPercent($row['occ_budget']),
                    'occ_ly' => self::fromPercent($row['occ_ly']),
                    'arr_actual' => self::n($row['arr_actual']),
                    'arr_budget' => self::n($row['arr_budget']),
                    'arr_ly' => self::n($row['arr_ly']),
                    'rev_actual' => self::n($row['rev_actual']),
                    'rev_budget' => self::n($row['rev_budget']),
                    'rev_ly' => self::n($row['rev_ly']),
                ],
            );
        }

        ActivityLog::record('updated', $this->week, 'Edited Section B (YTD Actual & Forecast)');
        $this->saved = true;
    }

    #[Computed]
    public function totals(): array
    {
        $rn = 0;
        $revA = $revB = $revL = 0.0;
        foreach ($this->rows as $row) {
            $rn += (int) self::n($row['rn_sold']);
            $revA += (float) self::n($row['rev_actual']);
            $revB += (float) self::n($row['rev_budget']);
            $revL += (float) self::n($row['rev_ly']);
        }

        return ['rn' => $rn, 'revA' => $revA, 'revB' => $revB, 'revL' => $revL];
    }

    private static function toPercent(?float $frac): ?float
    {
        return $frac === null ? null : round($frac * 100, 2);
    }

    private static function fromPercent(mixed $pct): ?float
    {
        $v = self::n($pct);

        return $v === null ? null : $v / 100;
    }

    private static function n(mixed $v): int|float|null
    {
        if ($v === null || $v === '') {
            return null;
        }

        return 0 + $v;
    }

    public function render()
    {
        return view('livewire.sections.monthly-stats');
    }
}
