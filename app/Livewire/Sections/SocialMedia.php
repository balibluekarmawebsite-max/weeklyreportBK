<?php

namespace App\Livewire\Sections;

use App\Models\ActivityLog;
use App\Models\ReportWeek;
use App\Models\SocialMediaMetric;
use Livewire\Component;

/**
 * Section H — Social Media Insight. Fixed metrics; growth and growth % computed.
 */
class SocialMedia extends Component
{
    public ReportWeek $week;

    public string $platform = 'Instagram';

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public bool $saved = false;

    public const METRICS = [
        'website_visit' => 'Website Visit',
        'profile_visit' => 'Profile Visit',
        'account_reached' => 'Account Reached',
        'impression' => 'Impression',
        'followers' => 'Followers',
    ];

    public function mount(ReportWeek $week): void
    {
        $this->week = $week;
        $existing = $week->socialMediaMetrics()->get()->keyBy('metric_key');
        $i = 0;
        foreach (self::METRICS as $key => $label) {
            $m = $existing->get($key);
            if ($m) {
                $this->platform = $m->platform;
            }
            $this->rows[] = [
                'metric_key' => $key,
                'label' => $label,
                'last_week' => $m?->last_week,
                'this_week' => $m?->this_week,
            ];
            $i++;
        }
    }

    public function save(): void
    {
        if ($this->week->isLocked()) {
            return;
        }

        $order = 0;
        foreach ($this->rows as $row) {
            SocialMediaMetric::updateOrCreate(
                ['report_week_id' => $this->week->id, 'metric_key' => $row['metric_key']],
                [
                    'platform' => $this->platform ?: 'Instagram',
                    'last_week' => $row['last_week'] === '' ? null : $row['last_week'],
                    'this_week' => $row['this_week'] === '' ? null : $row['this_week'],
                    'sort_order' => $order++,
                ],
            );
        }

        ActivityLog::record('updated', $this->week, 'Edited Section H (Social Media)');
        $this->saved = true;
    }

    public function growth(array $row): ?int
    {
        if (($row['last_week'] ?? null) === null || ($row['this_week'] ?? null) === null || $row['last_week'] === '' || $row['this_week'] === '') {
            return null;
        }

        return (int) $row['this_week'] - (int) $row['last_week'];
    }

    public function growthPercent(array $row): ?float
    {
        $last = (float) ($row['last_week'] ?? 0);
        if (! $last || ($row['this_week'] ?? null) === null || $row['this_week'] === '') {
            return null;
        }

        return (((int) $row['this_week'] - $last) / $last) * 100;
    }

    public function render()
    {
        return view('livewire.sections.social-media');
    }
}
