<?php

namespace Database\Seeders;

use App\Enums\ReportStatus;
use App\Models\ChannelMonthRn;
use App\Models\MonthlyStat;
use App\Models\OwnerChannelMix;
use App\Models\OwnerRepeaterMonth;
use App\Models\Property;
use App\Models\RateCodeProduction;
use App\Models\ReportWeek;
use App\Models\SegmentProduction;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Loads the real BKDS numbers (extracted from the sample weekly report) into
 * the current in-progress week, so the editor and dashboard show live data.
 * Source: database/seeders/data/bkds_week.json.
 */
class WeekDataSeeder extends Seeder
{
    public function run(): void
    {
        // Seed each property's current week from its own extracted data file,
        // so every property shows the right numbers.
        foreach (['BKDS', 'BKDU', 'BKV'] as $code) {
            $property = Property::where('code', $code)->first();
            if (! $property) {
                continue;
            }

            $week = $property->reportWeeks()
                ->where('status', ReportStatus::InProgress->value)
                ->latest('start_date')
                ->first()
                ?? $property->reportWeeks()->latest('start_date')->first();

            if (! $week) {
                continue;
            }

            $path = database_path('seeders/data/'.strtolower($code).'_week.json');
            if (! is_file($path)) {
                continue;
            }

            $this->seedWeek($week, json_decode(file_get_contents($path), true));
        }
    }

    private function seedWeek(ReportWeek $week, array $data): void
    {
        // Clear any previous data for an idempotent seed.
        $week->monthlyStats()->delete();
        $week->segmentProductions()->delete();
        $week->rateCodeProductions()->delete();
        $week->channelMonthRns()->delete();
        $week->ownerRepeaterMonths()->delete();
        $week->ownerChannelMix()->delete();

        // Section B
        foreach ($data['sectionB'] ?? [] as $r) {
            MonthlyStat::create(array_merge(['report_week_id' => $week->id], $r));
        }

        // Section C — keep real producing lines, drop subtotal rows.
        $this->seedProduction($week, $data['sectionC'] ?? [], SegmentProduction::class);

        // Section D
        $this->seedProduction($week, $data['sectionD'] ?? [], RateCodeProduction::class);

        // Sections E/F — channel room nights per year.
        // Merge any duplicate (year, source) rows from the source file by
        // summing their monthly values.
        $merged = [];
        foreach ($data['channels'] ?? [] as $c) {
            $key = $c['year'].'|'.$c['source'];
            if (! isset($merged[$key])) {
                $merged[$key] = ['year' => $c['year'], 'source' => $c['source'], 'months' => array_fill(0, 12, 0)];
            }
            foreach ($c['months'] as $i => $v) {
                $merged[$key]['months'][$i] += (int) $v;
            }
        }
        $order = 0;
        foreach ($merged as $c) {
            $m = $c['months'];
            ChannelMonthRn::create([
                'report_week_id' => $week->id,
                'year' => $c['year'],
                'source_label' => $c['source'],
                'jan' => $m[0], 'feb' => $m[1], 'mar' => $m[2], 'apr' => $m[3],
                'may' => $m[4], 'jun' => $m[5], 'jul' => $m[6], 'aug' => $m[7],
                'sep' => $m[8], 'oct' => $m[9], 'nov' => $m[10], 'dec' => $m[11],
                'sort_order' => $order++,
            ]);
        }

        // Owner Overview — repeater months.
        $order = 0;
        foreach ($data['ownerRepeater'] ?? [] as $r) {
            OwnerRepeaterMonth::create([
                'report_week_id' => $week->id,
                'label' => $this->monthLabel($r['label']),
                'room_nights' => $r['room_nights'] !== null ? (int) $r['room_nights'] : null,
                'revenue' => $r['revenue'],
                'sort_order' => $order++,
            ]);
        }

        // Owner Overview — channel mix.
        $order = 0;
        foreach ($data['ownerMix'] ?? [] as $r) {
            OwnerChannelMix::create([
                'report_week_id' => $week->id,
                'label' => $r['label'],
                'rn_sold' => $r['rn_sold'] !== null ? (int) $r['rn_sold'] : null,
                'gross_revenue' => $r['gross_revenue'],
                'sort_order' => $order++,
            ]);
        }

        // ---- Phase 4 written sections ----
        $week->overviewBlocks()->delete();
        $week->activities()->delete();
        $week->socialMediaMetrics()->delete();
        $week->trainings()->delete();
        $week->actionPlans()->delete();

        // Section A — overview blocks (seed what we extracted; others blank).
        $headings = \App\Livewire\Sections\Overview::BLOCKS;
        $ov = $data['overview'] ?? [];
        $order = 0;
        foreach ($headings as $key => $heading) {
            \App\Models\OverviewBlock::create([
                'report_week_id' => $week->id,
                'key' => $key,
                'heading' => $heading,
                'body' => $ov[$key] ?? null,
                'sort_order' => $order++,
            ]);
        }

        // G — Sales activity / G2 — E-commerce (full dates)
        $this->seedActivities($week, 'sales', $data['sales'] ?? [], 'subject');
        $this->seedActivities($week, 'ecommerce', $data['ecommerce'] ?? [], 'task');
        // (dateLabel keeps the day, unlike monthLabel)

        // H — Social media
        $order = 0;
        foreach ($data['social'] ?? [] as $r) {
            \App\Models\SocialMediaMetric::create([
                'report_week_id' => $week->id,
                'platform' => 'Instagram',
                'metric_key' => $r['metric'],
                'last_week' => $r['last_week'] !== null ? (int) $r['last_week'] : null,
                'this_week' => $r['this_week'] !== null ? (int) $r['this_week'] : null,
                'sort_order' => $order++,
            ]);
        }

        // I — Training
        $order = 0;
        foreach ($data['trainings'] ?? [] as $r) {
            \App\Models\Training::create([
                'report_week_id' => $week->id,
                'date_label' => $this->dateLabel($r['date'] ?? ''),
                'topic' => $r['topic'],
                'duration' => $r['duration'] ?? null,
                'trainer' => $r['trainer'] ?? null,
                'participants' => $r['participants'] ?? null,
                'sort_order' => $order++,
            ]);
        }

        // J — Action plan
        $order = 0;
        foreach ($data['actionPlan'] ?? [] as $r) {
            \App\Models\ActionPlan::create([
                'report_week_id' => $week->id,
                'category' => $r['category'] ?: null,
                'plan' => $r['plan'],
                'start_label' => $this->dateLabel($r['start'] ?? ''),
                'deadline_label' => $this->dateLabel($r['deadline'] ?? ''),
                'remark' => $r['remark'] ?? null,
                'sort_order' => $order++,
            ]);
        }
    }

    private function seedActivities(ReportWeek $week, string $department, array $rows, string $titleKey): void
    {
        $order = 0;
        foreach ($rows as $r) {
            $title = $r[$titleKey] ?? ($r['title'] ?? '');
            $notes = $r['notes'] ?? ($r['remarks'] ?? '');
            if (trim((string) $title) === '' && trim((string) $notes) === '') {
                continue;
            }
            \App\Models\Activity::create([
                'report_week_id' => $week->id,
                'department' => $department,
                'date_label' => $this->dateLabel($r['date'] ?? ''),
                'title' => $title ?: null,
                'notes' => $notes ?: null,
                'sort_order' => $order++,
            ]);
        }
    }

    private function seedProduction(ReportWeek $week, array $rows, string $model): void
    {
        $order = 0;
        foreach ($rows as $r) {
            $label = trim($r['label'] ?? '');
            if ($label === '' || str_contains(strtolower($label), 'total')) {
                continue; // totals are computed, not stored
            }
            if (($r['rn_sold'] ?? null) === null) {
                continue; // no production this week
            }
            $model::create([
                'report_week_id' => $week->id,
                'label' => $label,
                'rn_sold' => (int) $r['rn_sold'],
                'gross_revenue' => $r['gross_revenue'],
                'sort_order' => $order++,
            ]);
        }
    }

    private function monthLabel(string $label): string
    {
        // Normalise datetime-looking labels (e.g. "2026-02-01 00:00:00") to "February 2026".
        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $label)) {
                return Carbon::parse($label)->format('F Y');
            }
        } catch (\Throwable) {
            // fall through
        }

        return $label;
    }

    /** Full-date label (keeps the day), e.g. "25 Sep 2026". */
    private function dateLabel(string $label): string
    {
        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $label)) {
                return Carbon::parse($label)->format('d M Y');
            }
        } catch (\Throwable) {
            // fall through
        }

        return $label;
    }
}
