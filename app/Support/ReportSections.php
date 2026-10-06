<?php

namespace App\Support;

use App\Services\Vhp\VhpReservationCsvParser;

/**
 * Registry of the report's data sections for the per-section importer. Each
 * entry describes the section's columns (which drive both the AI extraction
 * schema and the preview table), what file types it accepts, and whether a
 * deterministic parser exists for it. Section A (Overview) is drafted in the
 * editor (Phase 7), so it is not a tabular upload target.
 */
class ReportSections
{
    /**
     * @return array<string, array{
     *   label: string,
     *   hint: string,
     *   columns: array<string, array{label: string, type: string}>,
     *   accepts: array<int, string>,
     *   parser: ?class-string,
     * }>
     */
    public static function all(): array
    {
        return [
            'monthly' => [
                'label' => 'B · Year to Date (Actual / Budget / LY)',
                'hint' => 'Per-month occupancy, ADR and revenue vs budget and last year. Occupancy is a percentage.',
                'columns' => [
                    'month' => ['label' => 'Month (1-12)', 'type' => 'int'],
                    'rn_sold' => ['label' => 'RN Sold', 'type' => 'int'],
                    'occ_actual' => ['label' => 'Occ Actual %', 'type' => 'percent'],
                    'occ_budget' => ['label' => 'Occ Budget %', 'type' => 'percent'],
                    'occ_ly' => ['label' => 'Occ LY %', 'type' => 'percent'],
                    'arr_actual' => ['label' => 'ADR Actual', 'type' => 'money'],
                    'arr_budget' => ['label' => 'ADR Budget', 'type' => 'money'],
                    'arr_ly' => ['label' => 'ADR LY', 'type' => 'money'],
                    'rev_actual' => ['label' => 'Revenue Actual', 'type' => 'money'],
                    'rev_budget' => ['label' => 'Revenue Budget', 'type' => 'money'],
                    'rev_ly' => ['label' => 'Revenue LY', 'type' => 'money'],
                ],
                'accepts' => ['csv', 'excel', 'image'],
                'parser' => null,
            ],
            'segment' => [
                'label' => 'C · Weekly Production by Market Segment',
                'hint' => 'Best from VHP "Reservation By Creation Date" CSV — parsed exactly, grouped by segment.',
                'columns' => [
                    'segment_group' => ['label' => 'Category', 'type' => 'text'],
                    'label' => ['label' => 'Agent / Source', 'type' => 'text'],
                    'rn_sold' => ['label' => 'RN Sold', 'type' => 'int'],
                    'gross_revenue' => ['label' => 'Gross Revenue', 'type' => 'money'],
                ],
                'accepts' => ['csv', 'image'],
                'parser' => VhpReservationCsvParser::class,
            ],
            'ratecode' => [
                'label' => 'D · Rate Code / Promotion',
                'hint' => 'Room nights and revenue per rate code / promotion.',
                'columns' => [
                    'label' => ['label' => 'Promotion / Rate Code', 'type' => 'text'],
                    'rn_sold' => ['label' => 'RN Sold', 'type' => 'int'],
                    'gross_revenue' => ['label' => 'Gross Revenue', 'type' => 'money'],
                ],
                'accepts' => ['csv', 'excel', 'image'],
                'parser' => null,
            ],
            'channels' => [
                'label' => 'E/F · Channel Inside (Room Nights)',
                'hint' => 'Room nights per source per month, with a year.',
                'columns' => [
                    'year' => ['label' => 'Year', 'type' => 'int'],
                    'source' => ['label' => 'Source', 'type' => 'text'],
                    'jan' => ['label' => 'Jan', 'type' => 'int'],
                    'feb' => ['label' => 'Feb', 'type' => 'int'],
                    'mar' => ['label' => 'Mar', 'type' => 'int'],
                    'apr' => ['label' => 'Apr', 'type' => 'int'],
                    'may' => ['label' => 'May', 'type' => 'int'],
                    'jun' => ['label' => 'Jun', 'type' => 'int'],
                    'jul' => ['label' => 'Jul', 'type' => 'int'],
                    'aug' => ['label' => 'Aug', 'type' => 'int'],
                    'sep' => ['label' => 'Sep', 'type' => 'int'],
                    'oct' => ['label' => 'Oct', 'type' => 'int'],
                    'nov' => ['label' => 'Nov', 'type' => 'int'],
                    'dec' => ['label' => 'Dec', 'type' => 'int'],
                ],
                'accepts' => ['csv', 'excel', 'image'],
                'parser' => null,
            ],
            'sales' => [
                'label' => 'G · Sales Activity',
                'hint' => 'Dated sales activities with a subject and notes.',
                'columns' => [
                    'date' => ['label' => 'Date', 'type' => 'text'],
                    'subject' => ['label' => 'Subject', 'type' => 'text'],
                    'notes' => ['label' => 'Notes', 'type' => 'text'],
                ],
                'accepts' => ['csv', 'excel', 'image'],
                'parser' => null,
            ],
            'ecommerce' => [
                'label' => 'G2 · E-commerce Activities',
                'hint' => 'Dated e-commerce tasks with remarks.',
                'columns' => [
                    'date' => ['label' => 'Date', 'type' => 'text'],
                    'task' => ['label' => 'Task', 'type' => 'text'],
                    'notes' => ['label' => 'Notes', 'type' => 'text'],
                ],
                'accepts' => ['csv', 'excel', 'image'],
                'parser' => null,
            ],
            'social' => [
                'label' => 'H · Social Media Insight',
                'hint' => 'Best from an Instagram / Meta insights screenshot — followers, reach, impressions, visits.',
                'columns' => [
                    'metric' => ['label' => 'Metric', 'type' => 'text'],
                    'last_week' => ['label' => 'Last Week', 'type' => 'int'],
                    'this_week' => ['label' => 'This Week', 'type' => 'int'],
                ],
                'accepts' => ['csv', 'excel', 'image'],
                'parser' => null,
            ],
            'trainings' => [
                'label' => 'I · Training',
                'hint' => 'Training sessions: date, topic, duration, trainer, participants.',
                'columns' => [
                    'date' => ['label' => 'Date', 'type' => 'text'],
                    'topic' => ['label' => 'Topic', 'type' => 'text'],
                    'duration' => ['label' => 'Duration', 'type' => 'text'],
                    'trainer' => ['label' => 'Trainer', 'type' => 'text'],
                    'participants' => ['label' => 'Participants', 'type' => 'text'],
                ],
                'accepts' => ['csv', 'excel', 'image'],
                'parser' => null,
            ],
            'actionplan' => [
                'label' => 'J · Next Week Action Plan',
                'hint' => 'Planned actions by category with start, deadline and remark.',
                'columns' => [
                    'category' => ['label' => 'Category', 'type' => 'text'],
                    'plan' => ['label' => 'Plan', 'type' => 'text'],
                    'start' => ['label' => 'Start', 'type' => 'text'],
                    'deadline' => ['label' => 'Deadline', 'type' => 'text'],
                    'remark' => ['label' => 'Remark', 'type' => 'text'],
                ],
                'accepts' => ['csv', 'excel', 'image'],
                'parser' => null,
            ],
            'owner_repeater' => [
                'label' => 'Owner · Repeater Guest (per month)',
                'hint' => 'Repeater room nights and revenue per month.',
                'columns' => [
                    'label' => ['label' => 'Month', 'type' => 'text'],
                    'room_nights' => ['label' => 'Room Nights', 'type' => 'int'],
                    'revenue' => ['label' => 'Revenue', 'type' => 'money'],
                ],
                'accepts' => ['csv', 'excel', 'image'],
                'parser' => null,
            ],
            'owner_mix' => [
                'label' => 'Owner · Channel Mix',
                'hint' => 'Room nights and revenue per source.',
                'columns' => [
                    'label' => ['label' => 'Source', 'type' => 'text'],
                    'rn_sold' => ['label' => 'RN Sold', 'type' => 'int'],
                    'gross_revenue' => ['label' => 'Gross Revenue', 'type' => 'money'],
                ],
                'accepts' => ['csv', 'excel', 'image'],
                'parser' => null,
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }
}
