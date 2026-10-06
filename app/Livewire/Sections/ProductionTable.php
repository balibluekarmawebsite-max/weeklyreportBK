<?php

namespace App\Livewire\Sections;

use App\Models\ActivityLog;
use App\Models\RateCodeProduction;
use App\Models\ReportWeek;
use App\Models\SegmentProduction;
use App\Support\ReportCalculator;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Sections C (market segment) and D (rate code / promotion) — line items with
 * room nights + gross revenue. ARR and % share are computed, never stored.
 */
class ProductionTable extends Component
{
    public ReportWeek $week;

    public string $kind = 'segment'; // segment | ratecode

    /** @var array<int, array{id:?int, label:string, rn_sold:?int, gross_revenue:?float}> */
    public array $rows = [];

    public bool $saved = false;

    public function mount(ReportWeek $week, string $kind = 'segment'): void
    {
        $this->week = $week;
        $this->kind = $kind;
        foreach ($this->query()->get() as $r) {
            $this->rows[] = [
                'id' => $r->id,
                'group' => $this->kind === 'segment' ? ($r->segment_group ?? '') : '',
                'label' => $r->label,
                'rn_sold' => $r->rn_sold,
                'gross_revenue' => $r->gross_revenue,
            ];
        }
        if (empty($this->rows)) {
            $this->addRow();
        }
    }

    public function addRow(): void
    {
        $this->rows[] = ['id' => null, 'group' => '', 'label' => '', 'rn_sold' => null, 'gross_revenue' => null];
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

        $class = $this->modelClass();
        $keepIds = [];
        $order = 0;

        foreach ($this->rows as $row) {
            if (trim((string) $row['label']) === '') {
                continue;
            }
            $attrs = [
                'label' => trim($row['label']),
                'rn_sold' => $row['rn_sold'] === '' ? null : $row['rn_sold'],
                'gross_revenue' => $row['gross_revenue'] === '' ? null : $row['gross_revenue'],
                'sort_order' => $order++,
            ];
            if ($this->kind === 'segment') {
                $attrs['segment_group'] = trim((string) ($row['group'] ?? '')) ?: null;
            }
            $model = $class::updateOrCreate(
                ['id' => $row['id'], 'report_week_id' => $this->week->id],
                $attrs,
            );
            $keepIds[] = $model->id;
        }

        // Delete rows removed in the UI.
        $this->query()->whereNotIn('id', $keepIds ?: [0])->delete();

        ActivityLog::record('updated', $this->week, "Edited Section {$this->sectionLetter()}");
        $this->saved = true;

        // Reload with fresh ids.
        $this->rows = [];
        $this->mount($this->week, $this->kind);
    }

    #[Computed]
    public function totals(): array
    {
        $objs = array_map(fn ($r) => (object) $r, $this->rows);

        return ReportCalculator::productionTotals($objs);
    }

    public function arr(array $row): ?float
    {
        return ReportCalculator::rate($row['gross_revenue'] ?: null, $row['rn_sold'] ?: null);
    }

    public function share(array $row): ?float
    {
        return ReportCalculator::sharePercent($row['rn_sold'] ?: 0, $this->totals['rn']);
    }

    private function modelClass(): string
    {
        return $this->kind === 'ratecode' ? RateCodeProduction::class : SegmentProduction::class;
    }

    private function query()
    {
        return $this->kind === 'ratecode'
            ? $this->week->rateCodeProductions()
            : $this->week->segmentProductions();
    }

    public function sectionLetter(): string
    {
        return $this->kind === 'ratecode' ? 'D' : 'C';
    }

    public function title(): string
    {
        return $this->kind === 'ratecode'
            ? 'D · Rate Code / Promotion'
            : 'C · Weekly Production by Market Segment';
    }

    public function labelHeading(): string
    {
        return $this->kind === 'ratecode' ? 'Promotion' : 'Source / Segment';
    }

    public function render()
    {
        return view('livewire.sections.production-table');
    }
}
