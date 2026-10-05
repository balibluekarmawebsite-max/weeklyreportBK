<?php

namespace App\Livewire\Sections;

use App\Models\ActivityLog;
use App\Models\OverviewBlock;
use App\Models\ReportWeek;
use Livewire\Component;

/**
 * Section A — Sales & Marketing Overview. A set of named text blocks.
 * (AI drafting of these blocks arrives in Phase 7.)
 */
class Overview extends Component
{
    public ReportWeek $week;

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public bool $saved = false;

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
        }

        ActivityLog::record('updated', $this->week, 'Edited Section A (Overview)');
        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.sections.overview');
    }
}
