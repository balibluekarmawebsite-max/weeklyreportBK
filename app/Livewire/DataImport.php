<?php

namespace App\Livewire;

use App\Enums\ReportStatus;
use App\Models\ActivityLog;
use App\Models\Import;
use App\Models\Property;
use App\Models\ReportWeek;
use App\Services\WeeklyReportImporter;
use App\Services\WeeklyReportParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Data Import — upload a weekly-report workbook, preview what was found,
 * then apply it to a report week. Each upload is logged in the imports table.
 */
class DataImport extends Component
{
    use WithFileUploads;

    #[Validate('required|file|mimes:xlsx,xls|max:20480')]
    public $file;

    public string $step = 'upload'; // upload | preview | done

    public ?array $parsed = null;

    public ?int $importId = null;

    public ?int $propertyId = null;

    public ?string $startDate = null;

    public array $summary = [];

    public ?int $appliedWeekId = null;

    public function updatedFile(): void
    {
        $this->validate();
        $this->reset(['parsed', 'summary', 'appliedWeekId']);

        $path = $this->file->getRealPath();
        $original = $this->file->getClientOriginalName();

        try {
            $parser = new WeeklyReportParser();
            $this->parsed = $parser->parse($path, $original);
        } catch (\Throwable $e) {
            Import::create([
                'user_id' => auth()->id(),
                'original_filename' => $original,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
            $this->addError('file', 'Could not read this file: '.$e->getMessage());

            return;
        }

        // Persist the uploaded file for the record.
        $stored = $this->file->store('imports');

        $meta = $this->parsed['meta'];
        $property = (new WeeklyReportImporter())->resolveProperty($meta);
        $this->propertyId = $property?->id;
        $this->startDate = $meta['start_date'];

        $import = Import::create([
            'user_id' => auth()->id(),
            'property_id' => $this->propertyId,
            'original_filename' => $original,
            'stored_path' => $stored,
            'status' => 'parsed',
            'period_label' => $meta['period_label'],
            'summary' => $this->sectionCounts(),
            'warnings' => $this->parsed['warnings'],
        ]);
        $this->importId = $import->id;
        $this->step = 'preview';
    }

    public function apply(): void
    {
        $this->validate([
            'propertyId' => 'required|exists:properties,id',
            'startDate' => 'required|date',
        ], [], ['propertyId' => 'property', 'startDate' => 'week start date']);

        $start = Carbon::parse($this->startDate);
        if (! $start->isFriday()) {
            $start = $start->previous(Carbon::FRIDAY);
        }
        $end = $start->copy()->addDays(6);

        $week = ReportWeek::where('property_id', $this->propertyId)
            ->whereDate('start_date', $start->toDateString())
            ->first();

        if ($week && $week->isLocked()) {
            $this->addError('startDate', 'That week is locked (approved/exported) and cannot be overwritten.');

            return;
        }

        if (! $week) {
            $week = ReportWeek::create([
                'property_id' => $this->propertyId,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'year' => (int) $start->isoFormat('GGGG'),
                'week_number' => (int) $start->isoFormat('W'),
                'label' => $start->format('d M').' – '.$end->format('d M Y'),
                'status' => ReportStatus::InProgress,
                'owner_id' => auth()->id(),
            ]);
        }

        $this->summary = (new WeeklyReportImporter())->applyToWeek($this->parsed, $week);

        if ($this->importId) {
            Import::where('id', $this->importId)->update([
                'report_week_id' => $week->id,
                'property_id' => $this->propertyId,
                'status' => 'applied',
                'applied_at' => now(),
            ]);
        }

        ActivityLog::record('imported', $week, 'Imported data from '.($this->parsed['meta']['source_filename'] ?? 'file'));

        $this->appliedWeekId = $week->id;
        $this->step = 'done';
    }

    public function reset_(): void
    {
        $this->reset(['file', 'parsed', 'importId', 'summary', 'appliedWeekId']);
        $this->step = 'upload';
    }

    public function sectionCounts(): array
    {
        if (! $this->parsed) {
            return [];
        }

        return [
            'B (monthly)' => count($this->parsed['sectionB'] ?? []),
            'C (segments)' => count($this->parsed['sectionC'] ?? []),
            'D (rate codes)' => count($this->parsed['sectionD'] ?? []),
            'E/F (channel rows)' => count($this->parsed['channels'] ?? []),
            'Owner repeater' => count($this->parsed['ownerRepeater'] ?? []),
            'Owner mix' => count($this->parsed['ownerMix'] ?? []),
        ];
    }

    public function render()
    {
        return view('livewire.data-import', [
            'properties' => Property::where('is_active', true)->orderBy('code')->get(),
            'history' => Import::with('property', 'reportWeek')->latest()->take(8)->get(),
        ]);
    }
}
