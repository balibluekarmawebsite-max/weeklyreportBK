<?php

namespace App\Livewire\Sections;

use App\Models\ActivityLog;
use App\Models\OverviewBlock;
use App\Models\ReportWeek;
use App\Services\Ai\AiException;
use App\Services\Ai\ReportNarrator;
use App\Services\ReportData;
use App\Support\ReportInspector;
use Livewire\Component;

/**
 * Section A — Sales & Marketing Overview. A set of named text blocks, each of
 * which can be drafted or rewritten with AI (Phase 7) and flagged "AI draft"
 * until a person edits and saves it.
 */
class Overview extends Component
{
    public ReportWeek $week;

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public bool $saved = false;

    public ?string $aiError = null;

    /** @var array<int, string> */
    public array $anomalies = [];

    public bool $anomaliesChecked = false;

    public const BLOCKS = [
        'financial' => '1. Financial',
        'market' => '2. Market Overview',
        'pace' => '3. Pace Report',
        'countries' => '4. Countries',
        'booking_window' => '5. Booking Window',
        'booking_ranking' => '6. Booking.com Ranking',
        'learning' => '7. Learning & Growth / Trainings',
    ];

    public function mount(ReportWeek $week): void
    {
        $this->week = $week;
        $existing = $week->overviewBlocks()->get()->keyBy('key');
        $order = 0;
        foreach (self::BLOCKS as $key => $heading) {
            $b = $existing->get($key);
            $this->rows[] = [
                'key' => $key,
                'heading' => $b?->heading ?: $heading,
                'body' => $b?->body,
                'ai_draft' => (bool) ($b?->ai_draft ?? false),
            ];
            $order++;
        }
    }

    public function save(): void
    {
        if ($this->week->isLocked()) {
            return;
        }

        $order = 0;
        foreach ($this->rows as $row) {
            OverviewBlock::updateOrCreate(
                ['report_week_id' => $this->week->id, 'key' => $row['key']],
                [
                    'heading' => $row['heading'],
                    'body' => $row['body'] ?: null,
                    'ai_draft' => false, // a person edited/approved it
                    'sort_order' => $order++,
                ],
            );
            $this->rows[$order - 1]['ai_draft'] = false;
        }

        ActivityLog::record('updated', $this->week, 'Edited Section A (Overview)');
        $this->saved = true;
    }

    /** Draft a block's body from the week's figures. */
    public function draftWithAi(int $i, ReportNarrator $narrator): void
    {
        $this->aiError = null;
        if ($this->week->isLocked() || ! isset($this->rows[$i])) {
            return;
        }

        try {
            $text = $narrator->draftBlock($this->week, $this->rows[$i]['key'], $this->rows[$i]['heading']);
        } catch (AiException $e) {
            $this->aiError = $e->getMessage();

            return;
        }

        $this->applyAi($i, $text);
    }

    /** Rewrite / shorten / translate the current body. */
    public function aiRewrite(int $i, string $mode, ReportNarrator $narrator): void
    {
        $this->aiError = null;
        if ($this->week->isLocked() || ! isset($this->rows[$i])) {
            return;
        }
        if (trim((string) ($this->rows[$i]['body'] ?? '')) === '') {
            $this->aiError = 'Write some text first, then let AI rewrite it.';

            return;
        }

        try {
            $text = $narrator->rewrite($this->week, 'overview', $this->rows[$i]['key'], (string) $this->rows[$i]['body'], $mode);
        } catch (AiException $e) {
            $this->aiError = $e->getMessage();

            return;
        }

        $this->applyAi($i, $text);
    }

    /** Store the AI text in the row and persist the block flagged as a draft. */
    private function applyAi(int $i, string $text): void
    {
        $this->rows[$i]['body'] = $text;
        $this->rows[$i]['ai_draft'] = true;

        if (! $this->week->isLocked()) {
            OverviewBlock::updateOrCreate(
                ['report_week_id' => $this->week->id, 'key' => $this->rows[$i]['key']],
                ['heading' => $this->rows[$i]['heading'], 'body' => $text, 'ai_draft' => true, 'sort_order' => $i],
            );
        }
    }

    /** Deterministic anomaly scan (no AI key needed). */
    public function checkAnomalies(): void
    {
        $this->anomalies = ReportInspector::anomalies(new ReportData($this->week));
        $this->anomaliesChecked = true;
    }

    public function render()
    {
        return view('livewire.sections.overview', [
            'aiReady' => app(ReportNarrator::class)->enabled(),
        ]);
    }
}
