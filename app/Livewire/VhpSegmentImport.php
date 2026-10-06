<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Import;
use App\Models\Property;
use App\Services\Vhp\VhpReservationCsvParser;
use App\Services\WeeklyImportPipeline;
use App\Services\WeeklyReportImporter;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Imports VHP's "Reservation By Creation Date" CSV into Section C (market
 * segment production), grouped by VHP's own Segment column. Only Section C is
 * replaced — every other section of the week is left untouched.
 */
class VhpSegmentImport extends Component
{
    use WithFileUploads;

    #[Validate('required|file|mimes:csv,txt|max:10240')]
    public $file;

    public string $step = 'upload'; // upload | preview | done

    public ?array $parsed = null;

    public ?int $propertyId = null;

    public ?string $propertyName = null;

    public ?string $startDate = null;

    public ?string $periodLabel = null;

    /** @var array<int, string> */
    public array $warnings = [];

    public ?int $appliedWeekId = null;

    public ?string $errorMessage = null;

    public function updatedFile(): void
    {
        $this->validate();
        $this->reset(['parsed', 'warnings', 'appliedWeekId', 'errorMessage']);

        try {
            $this->parsed = (new VhpReservationCsvParser)->parse($this->file->getRealPath());
        } catch (\Throwable $e) {
            $this->errorMessage = 'Could not read this CSV: '.$e->getMessage();

            return;
        }

        $meta = $this->parsed['meta'];
        $this->propertyName = $meta['property_name'];
        $this->propertyId = $this->resolveProperty($meta['property_name'])?->id;
        $this->startDate = $meta['start_date'];
        $this->periodLabel = $meta['period_label'];
        $this->warnings = $this->parsed['warnings'];
        $this->step = 'preview';
    }

    public function apply(): void
    {
        $this->errorMessage = null;
        $this->validate([
            'propertyId' => 'required|exists:properties,id',
            'startDate' => 'required|date',
        ], [], ['propertyId' => 'property', 'startDate' => 'week start date']);

        $resolved = (new WeeklyImportPipeline)->findOrCreateWeek($this->propertyId, $this->startDate, auth()->id());
        $week = $resolved['week'];

        if ($resolved['locked']) {
            $this->errorMessage = 'That week is locked (approved/exported) and cannot be overwritten.';

            return;
        }

        $count = (new WeeklyReportImporter)->applySegments($this->parsed['segments'], $week);

        Import::create([
            'user_id' => auth()->id(),
            'property_id' => $this->propertyId,
            'report_week_id' => $week->id,
            'source' => 'vhp',
            'original_filename' => $this->file->getClientOriginalName(),
            'status' => 'applied',
            'period_label' => $this->periodLabel,
            'summary' => ['C (segments)' => $count],
            'warnings' => $this->warnings,
            'applied_at' => now(),
        ]);

        ActivityLog::record('imported', $week, 'Imported Section C from VHP CSV ('.$count.' rows)', ['source' => 'vhp', 'section' => 'C']);

        $this->appliedWeekId = $week->id;
        $this->step = 'done';
    }

    public function resetForm(): void
    {
        $this->reset(['file', 'parsed', 'warnings', 'appliedWeekId', 'errorMessage', 'propertyName', 'periodLabel']);
        $this->step = 'upload';
    }

    private function resolveProperty(?string $name): ?Property
    {
        if (! $name) {
            return null;
        }
        $needle = strtoupper(trim($name));

        return Property::all()->first(fn (Property $p) => strtoupper(trim($p->name)) === $needle)
            ?? Property::all()->first(fn (Property $p) => str_contains($needle, strtoupper(trim($p->name))) || str_contains(strtoupper(trim($p->name)), $needle));
    }

    /**
     * Preview rows grouped by category, with per-group subtotals.
     *
     * @return array<string, array{rows: array<int, array<string,mixed>>, rn: int, revenue: float}>
     */
    public function grouped(): array
    {
        $out = [];
        foreach ($this->parsed['segments'] ?? [] as $s) {
            $g = $s['segment_group'] ?: 'Other';
            $out[$g] ??= ['rows' => [], 'rn' => 0, 'revenue' => 0.0];
            $out[$g]['rows'][] = $s;
            $out[$g]['rn'] += (int) $s['rn_sold'];
            $out[$g]['revenue'] += (float) $s['gross_revenue'];
        }

        return $out;
    }

    public function render()
    {
        return view('livewire.vhp-segment-import', [
            'properties' => Property::where('is_active', true)->orderBy('code')->get(),
        ]);
    }
}
