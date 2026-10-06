<?php

namespace App\Services;

use App\Livewire\Sections\Overview;
use App\Models\ActionPlan;
use App\Models\Activity;
use App\Models\ChannelMonthRn;
use App\Models\MonthlyStat;
use App\Models\OverviewBlock;
use App\Models\OwnerChannelMix;
use App\Models\OwnerRepeaterMonth;
use App\Models\Property;
use App\Models\RateCodeProduction;
use App\Models\ReportWeek;
use App\Models\SegmentProduction;
use App\Models\SocialMediaMetric;
use App\Models\Training;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Writes parsed weekly-report data into a report week's section tables.
 *
 * Each section can be applied on its own (the applyX methods replace only that
 * section — used by the per-section importers), or the whole workbook can be
 * applied at once via applyToWeek(). Both share the same insertX helpers, so a
 * single-section import behaves identically to a full import for that section.
 */
class WeeklyReportImporter
{
    /** Resolve the property a parsed file belongs to, by its code. */
    public function resolveProperty(array $meta): ?Property
    {
        $code = $meta['property_code'] ?? null;

        return $code ? Property::where('code', $code)->first() : null;
    }

    /**
     * Apply rows to a single section by its registry key (replaces only that
     * section). Returns the number of rows written.
     */
    public function applySection(string $key, array $rows, ReportWeek $week): int
    {
        return match ($key) {
            'monthly' => $this->applyMonthly($rows, $week),
            'segment' => $this->applySegments($rows, $week),
            'ratecode' => $this->applyRateCodes($rows, $week),
            'channels' => $this->applyChannels($rows, $week),
            'sales' => $this->applyActivities('sales', $rows, $week),
            'ecommerce' => $this->applyActivities('ecommerce', $rows, $week),
            'social' => $this->applySocial($rows, $week),
            'trainings' => $this->applyTrainings($rows, $week),
            'actionplan' => $this->applyActionPlans($rows, $week),
            'owner_repeater' => $this->applyOwnerRepeater($rows, $week),
            'owner_mix' => $this->applyOwnerMix($rows, $week),
            default => throw new \InvalidArgumentException("Unknown section [{$key}]."),
        };
    }

    // ---- Section-scoped applies (replace only that section) --------------

    /** @param array<int, array{month?:int, rn_sold?:int|null}> $rows MonthlyStat attributes */
    public function applyMonthly(array $rows, ReportWeek $week): int
    {
        return DB::transaction(function () use ($rows, $week) {
            $week->monthlyStats()->delete();

            return $this->insertMonthly($week, $rows);
        });
    }

    /**
     * Replace ONLY Section C (market segment production). Rows carry a
     * segment_group (category).
     *
     * @param  array<int, array{segment_group?: ?string, label: string, rn_sold?: int|null, gross_revenue?: float|null}>  $segments
     */
    public function applySegments(array $segments, ReportWeek $week): int
    {
        return DB::transaction(function () use ($segments, $week) {
            $week->segmentProductions()->delete();

            return $this->insertSegments($week, $segments);
        });
    }

    public function applyRateCodes(array $rows, ReportWeek $week): int
    {
        return DB::transaction(function () use ($rows, $week) {
            $week->rateCodeProductions()->delete();

            return $this->insertProduction($week, $rows, RateCodeProduction::class);
        });
    }

    /** @param array<int, array{year:int, source:string, months:array<int,int>}> $rows */
    public function applyChannels(array $rows, ReportWeek $week): int
    {
        return DB::transaction(function () use ($rows, $week) {
            $week->channelMonthRns()->delete();

            return $this->insertChannels($week, $rows);
        });
    }

    public function applyActivities(string $department, array $rows, ReportWeek $week): int
    {
        return DB::transaction(function () use ($department, $rows, $week) {
            $week->activities()->where('department', $department)->delete();
            $titleKey = $department === 'ecommerce' ? 'task' : 'subject';

            return $this->insertActivities($week, $department, $rows, $titleKey);
        });
    }

    public function applySocial(array $rows, ReportWeek $week): int
    {
        return DB::transaction(function () use ($rows, $week) {
            $week->socialMediaMetrics()->delete();

            return $this->insertSocial($week, $rows);
        });
    }

    public function applyTrainings(array $rows, ReportWeek $week): int
    {
        return DB::transaction(function () use ($rows, $week) {
            $week->trainings()->delete();

            return $this->insertTrainings($week, $rows);
        });
    }

    public function applyActionPlans(array $rows, ReportWeek $week): int
    {
        return DB::transaction(function () use ($rows, $week) {
            $week->actionPlans()->delete();

            return $this->insertActionPlans($week, $rows);
        });
    }

    /** @param array<string, ?string> $blocks  block key => body */
    public function applyOverview(array $blocks, ReportWeek $week): int
    {
        return DB::transaction(function () use ($blocks, $week) {
            $week->overviewBlocks()->delete();

            return $this->insertOverview($week, $blocks);
        });
    }

    public function applyOwnerRepeater(array $rows, ReportWeek $week): int
    {
        return DB::transaction(function () use ($rows, $week) {
            $week->ownerRepeaterMonths()->delete();

            return $this->insertOwnerRepeater($week, $rows);
        });
    }

    public function applyOwnerMix(array $rows, ReportWeek $week): int
    {
        return DB::transaction(function () use ($rows, $week) {
            $week->ownerChannelMix()->delete();

            return $this->insertOwnerMix($week, $rows);
        });
    }

    // ---- Whole-workbook apply -------------------------------------------

    /** Apply parsed data to the week. Returns a per-section count summary. */
    public function applyToWeek(array $data, ReportWeek $week): array
    {
        return DB::transaction(function () use ($data, $week) {
            // Replace existing section data for an idempotent import.
            $week->monthlyStats()->delete();
            $week->segmentProductions()->delete();
            $week->rateCodeProductions()->delete();
            $week->channelMonthRns()->delete();
            $week->ownerRepeaterMonths()->delete();
            $week->ownerChannelMix()->delete();
            $week->overviewBlocks()->delete();
            $week->activities()->delete();
            $week->socialMediaMetrics()->delete();
            $week->trainings()->delete();
            $week->actionPlans()->delete();

            return [
                'B (monthly)' => $this->insertMonthly($week, $data['sectionB'] ?? []),
                'C (segments)' => $this->insertProduction($week, $data['sectionC'] ?? [], SegmentProduction::class),
                'D (rate codes)' => $this->insertProduction($week, $data['sectionD'] ?? [], RateCodeProduction::class),
                'E/F (channel rows)' => $this->insertChannels($week, $data['channels'] ?? []),
                'Owner repeater' => $this->insertOwnerRepeater($week, $data['ownerRepeater'] ?? []),
                'Owner mix' => $this->insertOwnerMix($week, $data['ownerMix'] ?? []),
                'A (overview blocks)' => $this->insertOverview($week, $data['overview'] ?? []),
                'G (sales)' => $this->insertActivities($week, 'sales', $data['sales'] ?? [], 'subject'),
                'G2 (e-commerce)' => $this->insertActivities($week, 'ecommerce', $data['ecommerce'] ?? [], 'task'),
                'H (social metrics)' => $this->insertSocial($week, $data['social'] ?? []),
                'I (trainings)' => $this->insertTrainings($week, $data['trainings'] ?? []),
                'J (action plan)' => $this->insertActionPlans($week, $data['actionPlan'] ?? []),
            ];
        });
    }

    // ---- Insert helpers (no delete; shared by both paths) ---------------

    private function insertMonthly(ReportWeek $week, array $rows): int
    {
        $n = 0;
        foreach ($rows as $r) {
            if (! isset($r['month'])) {
                continue;
            }
            MonthlyStat::create(array_merge(['report_week_id' => $week->id], $r));
            $n++;
        }

        return $n;
    }

    private function insertSegments(ReportWeek $week, array $rows): int
    {
        $order = 0;
        foreach ($rows as $s) {
            $label = trim((string) ($s['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            SegmentProduction::create([
                'report_week_id' => $week->id,
                'segment_group' => ($s['segment_group'] ?? null) ?: null,
                'label' => $label,
                'rn_sold' => $this->intOrNull($s['rn_sold'] ?? null),
                'gross_revenue' => $s['gross_revenue'] ?? null,
                'sort_order' => $order++,
            ]);
        }

        return $order;
    }

    private function insertProduction(ReportWeek $week, array $rows, string $model): int
    {
        $order = 0;
        foreach ($rows as $r) {
            $label = trim((string) ($r['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $model::create([
                'report_week_id' => $week->id,
                'label' => $label,
                'rn_sold' => $this->intOrNull($r['rn_sold'] ?? null),
                'gross_revenue' => $r['gross_revenue'] ?? null,
                'sort_order' => $order++,
            ]);
        }

        return $order;
    }

    private function insertChannels(ReportWeek $week, array $channels): int
    {
        // Merge duplicate (year, source) rows by summing months.
        $merged = [];
        foreach ($channels as $c) {
            $key = ($c['year'] ?? '').'|'.($c['source'] ?? '');
            if (! isset($merged[$key])) {
                $merged[$key] = ['year' => $c['year'] ?? null, 'source' => $c['source'] ?? '', 'months' => array_fill(0, 12, 0)];
            }
            foreach (($c['months'] ?? []) as $i => $v) {
                $merged[$key]['months'][$i] += (int) $v;
            }
        }

        $months = ChannelMonthRn::MONTHS;
        $order = 0;
        foreach ($merged as $c) {
            if (! $c['year'] || trim((string) $c['source']) === '') {
                continue;
            }
            $attrs = [
                'report_week_id' => $week->id,
                'year' => (int) $c['year'],
                'source_label' => $c['source'],
                'sort_order' => $order++,
            ];
            foreach ($months as $i => $col) {
                $attrs[$col] = (int) $c['months'][$i];
            }
            ChannelMonthRn::create($attrs);
        }

        return $order;
    }

    private function insertActivities(ReportWeek $week, string $department, array $rows, string $titleKey): int
    {
        $order = 0;
        foreach ($rows as $r) {
            $title = $r[$titleKey] ?? ($r['title'] ?? '');
            $notes = $r['notes'] ?? ($r['remarks'] ?? '');
            if (trim((string) $title) === '' && trim((string) $notes) === '') {
                continue;
            }
            Activity::create([
                'report_week_id' => $week->id,
                'department' => $department,
                'date_label' => $this->dateLabel($r['date'] ?? ''),
                'title' => $title ?: null,
                'notes' => $notes ?: null,
                'sort_order' => $order++,
            ]);
        }

        return $order;
    }

    private function insertSocial(ReportWeek $week, array $rows): int
    {
        $order = 0;
        foreach ($rows as $r) {
            $metric = trim((string) ($r['metric'] ?? $r['metric_key'] ?? ''));
            if ($metric === '') {
                continue;
            }
            SocialMediaMetric::create([
                'report_week_id' => $week->id,
                'platform' => $r['platform'] ?? 'Instagram',
                'metric_key' => $metric,
                'last_week' => $this->intOrNull($r['last_week'] ?? null),
                'this_week' => $this->intOrNull($r['this_week'] ?? null),
                'sort_order' => $order++,
            ]);
        }

        return $order;
    }

    private function insertTrainings(ReportWeek $week, array $rows): int
    {
        $order = 0;
        foreach ($rows as $r) {
            $topic = trim((string) ($r['topic'] ?? ''));
            if ($topic === '') {
                continue;
            }
            Training::create([
                'report_week_id' => $week->id,
                'date_label' => $this->dateLabel($r['date'] ?? ''),
                'topic' => $topic,
                'duration' => ($r['duration'] ?? null) ?: null,
                'trainer' => ($r['trainer'] ?? null) ?: null,
                'participants' => ($r['participants'] ?? null) ?: null,
                'sort_order' => $order++,
            ]);
        }

        return $order;
    }

    private function insertActionPlans(ReportWeek $week, array $rows): int
    {
        $order = 0;
        foreach ($rows as $r) {
            $plan = trim((string) ($r['plan'] ?? ''));
            if ($plan === '') {
                continue;
            }
            ActionPlan::create([
                'report_week_id' => $week->id,
                'category' => ($r['category'] ?? null) ?: null,
                'plan' => $plan,
                'start_label' => $this->dateLabel($r['start'] ?? ''),
                'deadline_label' => $this->dateLabel($r['deadline'] ?? ''),
                'remark' => ($r['remark'] ?? null) ?: null,
                'sort_order' => $order++,
            ]);
        }

        return $order;
    }

    private function insertOverview(ReportWeek $week, array $blocks): int
    {
        $order = 0;
        $filled = 0;
        foreach (Overview::BLOCKS as $key => $heading) {
            $body = $blocks[$key] ?? null;
            OverviewBlock::create([
                'report_week_id' => $week->id,
                'key' => $key,
                'heading' => $heading,
                'body' => $body ?: null,
                'sort_order' => $order++,
            ]);
            if ($body) {
                $filled++;
            }
        }

        return $filled;
    }

    private function insertOwnerRepeater(ReportWeek $week, array $rows): int
    {
        $order = 0;
        foreach ($rows as $r) {
            $label = trim((string) ($r['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            OwnerRepeaterMonth::create([
                'report_week_id' => $week->id,
                'label' => $label,
                'room_nights' => $this->intOrNull($r['room_nights'] ?? null),
                'revenue' => $r['revenue'] ?? null,
                'sort_order' => $order++,
            ]);
        }

        return $order;
    }

    private function insertOwnerMix(ReportWeek $week, array $rows): int
    {
        $order = 0;
        foreach ($rows as $r) {
            $label = trim((string) ($r['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            OwnerChannelMix::create([
                'report_week_id' => $week->id,
                'label' => $label,
                'rn_sold' => $this->intOrNull($r['rn_sold'] ?? null),
                'gross_revenue' => $r['gross_revenue'] ?? null,
                'sort_order' => $order++,
            ]);
        }

        return $order;
    }

    private function dateLabel(string $label): string
    {
        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $label)) {
                return Carbon::parse($label)->format('d M Y');
            }
        } catch (\Throwable) {
            // keep original
        }

        return $label;
    }

    private function intOrNull($v): ?int
    {
        return $v === null || $v === '' ? null : (int) $v;
    }
}
