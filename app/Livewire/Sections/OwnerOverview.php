<?php

namespace App\Livewire\Sections;

use App\Models\ActivityLog;
use App\Models\OwnerChannelMix;
use App\Models\OwnerRepeaterMonth;
use App\Models\ReportWeek;
use App\Support\ReportCalculator;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Owner Overview — repeater-guest 12-month performance (room nights, ADR,
 * revenue) and channel mix (room nights, ARR, %). ADR/ARR/% are computed.
 */
class OwnerOverview extends Component
{
    public ReportWeek $week;

    /** @var array<int, array<string, mixed>> */
    public array $repeater = [];

    /** @var array<int, array<string, mixed>> */
    public array $mix = [];

    public bool $saved = false;

    public function mount(ReportWeek $week): void
    {
        $this->week = $week;
        foreach ($week->ownerRepeaterMonths()->get() as $r) {
            $this->repeater[] = ['id' => $r->id, 'label' => $r->label, 'room_nights' => $r->room_nights, 'revenue' => $r->revenue];
        }
        foreach ($week->ownerChannelMix()->get() as $r) {
            $this->mix[] = ['id' => $r->id, 'label' => $r->label, 'rn_sold' => $r->rn_sold, 'gross_revenue' => $r->gross_revenue];
        }
        if (empty($this->repeater)) {
            $this->addRepeater();
        }
        if (empty($this->mix)) {
            $this->addMix();
        }
    }

    public function addRepeater(): void
    {
        $this->repeater[] = ['id' => null, 'label' => '', 'room_nights' => null, 'revenue' => null];
    }

    public function removeRepeater(int $i): void
    {
        unset($this->repeater[$i]);
        $this->repeater = array_values($this->repeater);
    }

    public function addMix(): void
    {
        $this->mix[] = ['id' => null, 'label' => '', 'rn_sold' => null, 'gross_revenue' => null];
    }

    public function removeMix(int $i): void
    {
        unset($this->mix[$i]);
        $this->mix = array_values($this->mix);
    }

    public function save(): void
    {
        if ($this->week->isLocked()) {
            return;
        }

        $keep = [];
        $order = 0;
        foreach ($this->repeater as $row) {
            if (trim((string) $row['label']) === '') {
                continue;
            }
            $m = OwnerRepeaterMonth::updateOrCreate(
                ['id' => $row['id'], 'report_week_id' => $this->week->id],
                ['label' => trim($row['label']), 'room_nights' => $row['room_nights'] ?: null, 'revenue' => $row['revenue'] ?: null, 'sort_order' => $order++],
            );
            $keep[] = $m->id;
        }
        $this->week->ownerRepeaterMonths()->whereNotIn('id', $keep ?: [0])->delete();

        $keep = [];
        $order = 0;
        foreach ($this->mix as $row) {
            if (trim((string) $row['label']) === '') {
                continue;
            }
            $m = OwnerChannelMix::updateOrCreate(
                ['id' => $row['id'], 'report_week_id' => $this->week->id],
                ['label' => trim($row['label']), 'rn_sold' => $row['rn_sold'] ?: null, 'gross_revenue' => $row['gross_revenue'] ?: null, 'sort_order' => $order++],
            );
            $keep[] = $m->id;
        }
        $this->week->ownerChannelMix()->whereNotIn('id', $keep ?: [0])->delete();

        ActivityLog::record('updated', $this->week, 'Edited Owner Overview');
        $this->saved = true;
        $this->repeater = [];
        $this->mix = [];
        $this->mount($this->week);
    }

    public function adr(array $row): ?float
    {
        return ReportCalculator::rate($row['revenue'] ?: null, $row['room_nights'] ?: null);
    }

    public function mixArr(array $row): ?float
    {
        return ReportCalculator::rate($row['gross_revenue'] ?: null, $row['rn_sold'] ?: null);
    }

    #[Computed]
    public function repeaterTotals(): array
    {
        $rn = array_sum(array_map(fn ($r) => (int) ($r['room_nights'] ?: 0), $this->repeater));
        $rev = array_sum(array_map(fn ($r) => (float) ($r['revenue'] ?: 0), $this->repeater));

        return ['rn' => $rn, 'rev' => $rev, 'adr' => ReportCalculator::rate($rev, $rn)];
    }

    #[Computed]
    public function mixTotals(): array
    {
        $objs = array_map(fn ($r) => (object) $r, $this->mix);

        return ReportCalculator::productionTotals($objs);
    }

    public function mixShare(array $row): ?float
    {
        return ReportCalculator::sharePercent($row['rn_sold'] ?: 0, $this->mixTotals['rn']);
    }

    public function render()
    {
        return view('livewire.sections.owner-overview');
    }
}
