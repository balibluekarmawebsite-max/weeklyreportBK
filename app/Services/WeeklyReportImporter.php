<?php

namespace App\Services;

use App\Models\ChannelMonthRn;
use App\Models\MonthlyStat;
use App\Models\OwnerChannelMix;
use App\Models\OwnerRepeaterMonth;
use App\Models\Property;
use App\Models\RateCodeProduction;
use App\Models\ReportWeek;
use App\Models\SegmentProduction;
use Illuminate\Support\Facades\DB;

/**
 * Writes parsed weekly-report data into a report week's section tables.
 * Existing section rows for the week are replaced, so re-importing is safe.
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
     * Apply parsed data to the week. Returns a per-section count summary.
     */
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

            $summary = [];

            // Section B
            $n = 0;
            foreach ($data['sectionB'] ?? [] as $r) {
                MonthlyStat::create(array_merge(['report_week_id' => $week->id], $r));
                $n++;
            }
            $summary['B (monthly)'] = $n;

            $summary['C (segments)'] = $this->applyProduction($week, $data['sectionC'] ?? [], SegmentProduction::class);
            $summary['D (rate codes)'] = $this->applyProduction($week, $data['sectionD'] ?? [], RateCodeProduction::class);
            $summary['E/F (channel rows)'] = $this->applyChannels($week, $data['channels'] ?? []);

            // Owner repeater
            $n = 0;
            $order = 0;
            foreach ($data['ownerRepeater'] ?? [] as $r) {
                OwnerRepeaterMonth::create([
                    'report_week_id' => $week->id,
                    'label' => $r['label'],
                    'room_nights' => $this->intOrNull($r['room_nights'] ?? null),
                    'revenue' => $r['revenue'] ?? null,
                    'sort_order' => $order++,
                ]);
                $n++;
            }
            $summary['Owner repeater'] = $n;

            // Owner mix
            $n = 0;
            $order = 0;
            foreach ($data['ownerMix'] ?? [] as $r) {
                OwnerChannelMix::create([
                    'report_week_id' => $week->id,
                    'label' => $r['label'],
                    'rn_sold' => $this->intOrNull($r['rn_sold'] ?? null),
                    'gross_revenue' => $r['gross_revenue'] ?? null,
                    'sort_order' => $order++,
                ]);
                $n++;
            }
            $summary['Owner mix'] = $n;

            return $summary;
        });
    }

    private function applyProduction(ReportWeek $week, array $rows, string $model): int
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

    private function applyChannels(ReportWeek $week, array $channels): int
    {
        // Merge duplicate (year, source) rows by summing months.
        $merged = [];
        foreach ($channels as $c) {
            $key = $c['year'].'|'.$c['source'];
            if (! isset($merged[$key])) {
                $merged[$key] = ['year' => $c['year'], 'source' => $c['source'], 'months' => array_fill(0, 12, 0)];
            }
            foreach ($c['months'] as $i => $v) {
                $merged[$key]['months'][$i] += (int) $v;
            }
        }

        $months = ChannelMonthRn::MONTHS;
        $order = 0;
        foreach ($merged as $c) {
            $attrs = [
                'report_week_id' => $week->id,
                'year' => $c['year'],
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

    private function intOrNull($v): ?int
    {
        return $v === null ? null : (int) $v;
    }
}
