<?php

namespace App\Livewire\Sections;

use App\Models\ActivityLog;
use App\Models\ChannelMonthRn;
use App\Models\ReportWeek;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Sections E/F — Channel Inside (Room Nights). One year at a time; each source
 * has 12 monthly values. YTD and % share are computed. Prior years are kept and
 * selectable.
 */
class ChannelGrid extends Component
{
    public ReportWeek $week;

    public int $year;

    /** @var array<int, int> */
    public array $years = [];

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public bool $saved = false;

    public function mount(ReportWeek $week): void
    {
        $this->week = $week;
        $this->years = $week->channelMonthRns()->distinct()->orderBy('year')->pluck('year')->all();
        if (empty($this->years)) {
            $this->years = [(int) ($week->year ?: now()->year)];
        }
        $this->year = (int) end($this->years); // most recent year
        $this->loadRows();
    }

    public function updatedYear(): void
    {
        $this->year = (int) $this->year;
        $this->loadRows();
    }

    public function loadRows(): void
    {
        $this->rows = [];
        $records = $this->week->channelMonthRns()->where('year', $this->year)->orderBy('sort_order')->get();
        foreach ($records as $r) {
            $row = ['id' => $r->id, 'source_label' => $r->source_label];
            foreach (ChannelMonthRn::MONTHS as $m) {
                $row[$m] = $r->{$m};
            }
            $this->rows[] = $row;
        }
        if (empty($this->rows)) {
            $this->addRow();
        }
    }

    public function addRow(): void
    {
        $row = ['id' => null, 'source_label' => ''];
        foreach (ChannelMonthRn::MONTHS as $m) {
            $row[$m] = 0;
        }
        $this->rows[] = $row;
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function save(): void
    {
        if ($this->week->isLocked()) {
            return;
        }

        $keepIds = [];
        $order = 0;
        foreach ($this->rows as $row) {
            if (trim((string) $row['source_label']) === '') {
                continue;
            }
            $attrs = ['source_label' => trim($row['source_label']), 'sort_order' => $order++];
            foreach (ChannelMonthRn::MONTHS as $m) {
                $attrs[$m] = (int) ($row[$m] ?: 0);
            }
            $model = ChannelMonthRn::updateOrCreate(
                ['id' => $row['id'], 'report_week_id' => $this->week->id, 'year' => $this->year],
                $attrs,
            );
            $keepIds[] = $model->id;
        }

        $this->week->channelMonthRns()
            ->where('year', $this->year)
            ->whereNotIn('id', $keepIds ?: [0])
            ->delete();

        ActivityLog::record('updated', $this->week, "Edited Section E/F channels ({$this->year})");
        $this->saved = true;
        $this->years = $this->week->channelMonthRns()->distinct()->orderBy('year')->pluck('year')->all();
        $this->loadRows();
    }

    public function rowYtd(array $row): int
    {
        return (int) array_sum(array_map(fn ($m) => (int) ($row[$m] ?: 0), ChannelMonthRn::MONTHS));
    }

    #[Computed]
    public function grandYtd(): int
    {
        $sum = 0;
        foreach ($this->rows as $row) {
            $sum += $this->rowYtd($row);
        }

        return $sum;
    }

    public function render()
    {
        return view('livewire.sections.channel-grid');
    }
}
