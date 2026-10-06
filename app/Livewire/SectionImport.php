<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Import;
use App\Models\ReportWeek;
use App\Services\Ai\AiException;
use App\Services\Ai\SectionExtractor;
use App\Services\Vhp\VhpReservationCsvParser;
use App\Services\WeeklyReportImporter;
use App\Support\ReportSections;
use App\Support\Workspace;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Per-section smart importer. A grid of section cards; opening a card lets you
 * upload a CSV/Excel or a screenshot for just that section. Known formats
 * (Section C's VHP CSV) are parsed exactly; anything else is read by AI into an
 * editable draft you review before Apply. Only the chosen section is replaced.
 */
class SectionImport extends Component
{
    use WithFileUploads;

    public ?int $weekId = null;

    public string $active = '';   // section key being imported, '' = show grid

    #[Validate('required|file|mimes:csv,txt,xlsx,xls,png,jpg,jpeg,webp|max:10240')]
    public $file;

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public bool $aiDraft = false;

    public bool $previewing = false;

    /** @var array<int, string> */
    public array $warnings = [];

    public ?string $errorMessage = null;

    public ?string $notice = null;

    public function mount(): void
    {
        $property = Workspace::currentProperty();
        $this->weekId = $property
            ? $property->reportWeeks()->latest('start_date')->value('id')
            : null;
    }

    public function openSection(string $key): void
    {
        if (! ReportSections::exists($key)) {
            return;
        }
        $this->active = $key;
        $this->reset(['file', 'rows', 'aiDraft', 'previewing', 'warnings', 'errorMessage', 'notice']);
    }

    public function close(): void
    {
        $this->reset(['active', 'file', 'rows', 'aiDraft', 'previewing', 'warnings', 'errorMessage', 'notice']);
    }

    public function updatedFile(): void
    {
        $this->validate();
        $this->reset(['rows', 'warnings', 'errorMessage', 'notice', 'previewing']);

        $section = ReportSections::get($this->active);
        $path = $this->file->getRealPath();
        $kind = $this->fileKind();

        try {
            if ($this->active === 'segment' && $kind !== 'image' && $section['parser'] === VhpReservationCsvParser::class) {
                // Exact VHP parser (no AI) for the reservation CSV.
                $parsed = (new VhpReservationCsvParser)->parse($path);
                $this->rows = $parsed['segments'];
                $this->warnings = $parsed['warnings'];
                $this->aiDraft = false;
            } else {
                $extractor = app(SectionExtractor::class);
                if (! $extractor->enabled()) {
                    $this->errorMessage = 'Reading this file needs AI. Add your GROQ_API_KEY in Settings/.env, or upload the exact VHP CSV for Section C.';

                    return;
                }
                $images = $kind === 'image' ? [$path] : [];
                $csvText = $kind === 'image' ? null : $this->toCsvText($path, $kind);
                $this->rows = $extractor->extract($this->active, $this->week(), $csvText, $images);
                $this->aiDraft = true;
            }
        } catch (AiException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Could not read this file: '.$e->getMessage();

            return;
        }

        if (empty($this->rows)) {
            $this->errorMessage = 'No rows could be read from this file. Try a clearer screenshot or a CSV export.';

            return;
        }
        $this->previewing = true;
    }

    public function addRow(): void
    {
        $this->rows[] = collect(ReportSections::get($this->active)['columns'])->map(fn () => null)->all();
    }

    public function removeRow(int $i): void
    {
        unset($this->rows[$i]);
        $this->rows = array_values($this->rows);
    }

    public function apply(): void
    {
        $this->errorMessage = null;
        $week = $this->week();
        if (! $week) {
            $this->errorMessage = 'No report week selected.';

            return;
        }
        if ($week->isLocked()) {
            $this->errorMessage = 'That week is locked (approved/exported) and cannot be overwritten.';

            return;
        }

        $rows = $this->active === 'channels' ? $this->channelRows($this->rows) : $this->rows;
        $count = (new WeeklyReportImporter)->applySection($this->active, $rows, $week);

        Import::create([
            'user_id' => auth()->id(),
            'property_id' => $week->property_id,
            'report_week_id' => $week->id,
            'source' => ($this->active === 'segment' && ! $this->aiDraft) ? 'vhp' : 'manual',
            'original_filename' => $this->file?->getClientOriginalName() ?? 'upload',
            'status' => 'applied',
            'summary' => [ReportSections::get($this->active)['label'] => $count],
            'warnings' => $this->warnings,
            'applied_at' => now(),
        ]);

        ActivityLog::record('imported', $week, 'Imported '.ReportSections::get($this->active)['label']." ({$count} rows)", ['section' => $this->active]);

        $this->notice = $count.' row(s) applied to '.ReportSections::get($this->active)['label'].'. Other sections were not changed.';
        $this->reset(['file', 'rows', 'aiDraft', 'previewing', 'warnings']);
    }

    // ---- helpers --------------------------------------------------------

    private function week(): ?ReportWeek
    {
        return $this->weekId ? ReportWeek::find($this->weekId) : null;
    }

    private function fileKind(): string
    {
        $ext = strtolower($this->file->getClientOriginalExtension());
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            return 'image';
        }
        if (in_array($ext, ['xlsx', 'xls'], true)) {
            return 'excel';
        }

        return 'csv';
    }

    private function toCsvText(string $path, string $kind): string
    {
        if ($kind === 'csv') {
            return (string) file_get_contents($path);
        }
        // Excel → CSV text for the AI.
        $book = IOFactory::load($path);
        $lines = [];
        foreach ($book->getActiveSheet()->toArray() as $row) {
            $lines[] = implode(',', array_map(fn ($c) => '"'.str_replace('"', '""', (string) $c).'"', $row));
        }

        return implode("\n", $lines);
    }

    /** Fold flat jan..dec columns into the {year, source, months[12]} shape the importer expects. */
    private function channelRows(array $rows): array
    {
        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

        return array_map(fn ($r) => [
            'year' => $r['year'] ?? null,
            'source' => $r['source'] ?? '',
            'months' => array_map(fn ($m) => (int) ($r[$m] ?? 0), $months),
        ], $rows);
    }

    public function render()
    {
        $week = $this->week();
        $property = Workspace::currentProperty();

        return view('livewire.section-import', [
            'sections' => ReportSections::all(),
            'week' => $week,
            'weeks' => $property ? $property->reportWeeks()->latest('start_date')->take(12)->get() : collect(),
            'filled' => $week ? $this->sectionStatus($week) : [],
            'aiReady' => app(SectionExtractor::class)->enabled(),
        ]);
    }

    /** @return array<string, bool> section key => has data */
    private function sectionStatus(ReportWeek $week): array
    {
        return [
            'monthly' => $week->monthlyStats()->exists(),
            'segment' => $week->segmentProductions()->exists(),
            'ratecode' => $week->rateCodeProductions()->exists(),
            'channels' => $week->channelMonthRns()->exists(),
            'sales' => $week->activities()->where('department', 'sales')->exists(),
            'ecommerce' => $week->activities()->where('department', 'ecommerce')->exists(),
            'social' => $week->socialMediaMetrics()->exists(),
            'trainings' => $week->trainings()->exists(),
            'actionplan' => $week->actionPlans()->exists(),
            'owner_repeater' => $week->ownerRepeaterMonths()->exists(),
            'owner_mix' => $week->ownerChannelMix()->exists(),
        ];
    }
}
