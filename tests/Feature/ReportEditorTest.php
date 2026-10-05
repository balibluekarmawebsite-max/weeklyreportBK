<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Livewire\Sections\MonthlyStats;
use App\Livewire\Sections\ProductionTable;
use App\Models\ReportWeek;
use App\Models\SegmentProduction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'ota@bluekarmasecrets.com')->firstOrFail());
    }

    private function week(): ReportWeek
    {
        return ReportWeek::where('status', ReportStatus::InProgress->value)->firstOrFail();
    }

    public function test_each_data_section_renders(): void
    {
        $week = $this->week();
        foreach (['B', 'C', 'D', 'EF', 'OWNER'] as $section) {
            $this->get(route('reports.show', $week).'?section='.$section)->assertOk();
        }
    }

    public function test_seeded_week_shows_real_segment_total(): void
    {
        // Section C total RN for the seeded BKDS week is 142 (matches the sheet).
        $this->assertSame(142, $this->week()->segmentProductions()->sum('rn_sold'));
    }

    public function test_monthly_stats_component_saves_occupancy_as_fraction(): void
    {
        $week = $this->week();

        Livewire::test(MonthlyStats::class, ['week' => $week])
            ->set('rows.10.occ_actual', 88.5)   // entered as a percentage
            ->set('rows.10.rn_sold', 500)
            ->call('save')
            ->assertSet('saved', true);

        $oct = $week->monthlyStats()->where('month', 10)->first();
        $this->assertEqualsWithDelta(0.885, (float) $oct->occ_actual, 0.0001);
        $this->assertSame(500, $oct->rn_sold);
    }

    public function test_production_table_adds_and_saves_a_row(): void
    {
        $week = $this->week();
        $before = $week->segmentProductions()->count();

        Livewire::test(ProductionTable::class, ['week' => $week, 'kind' => 'segment'])
            ->call('addRow')
            ->set('rows.'.$before.'.label', 'Test Channel')
            ->set('rows.'.$before.'.rn_sold', 10)
            ->set('rows.'.$before.'.gross_revenue', 20_000_000)
            ->call('save')
            ->assertSet('saved', true);

        $this->assertDatabaseHas('segment_productions', [
            'report_week_id' => $week->id,
            'label' => 'Test Channel',
            'rn_sold' => 10,
        ]);
    }

    public function test_locked_week_does_not_save(): void
    {
        $week = ReportWeek::where('status', ReportStatus::Approved->value)->firstOrFail();

        Livewire::test(MonthlyStats::class, ['week' => $week])
            ->set('rows.1.rn_sold', 999)
            ->call('save');

        $this->assertDatabaseMissing('monthly_stats', [
            'report_week_id' => $week->id,
            'month' => 1,
            'rn_sold' => 999,
        ]);
    }

    public function test_creating_a_week_snaps_to_friday(): void
    {
        $property = $this->week()->property;

        // A fresh Friday with no existing week: 14 Aug 2026 is a Friday.
        $this->assertSame(0, ReportWeek::where('property_id', $property->id)->whereDate('start_date', '2026-08-14')->count());

        // 19 Aug 2026 is a Wednesday -> should snap back to Fri 14 Aug 2026.
        $this->post(route('reports.store'), [
            'property_id' => $property->id,
            'start_date' => '2026-08-19',
        ])->assertRedirect();

        $week = ReportWeek::where('property_id', $property->id)->whereDate('start_date', '2026-08-14')->first();
        $this->assertNotNull($week);
        $this->assertSame('2026-08-20', $week->end_date->toDateString());
    }

    public function test_creating_an_existing_week_does_not_duplicate(): void
    {
        $property = $this->week()->property;
        $before = ReportWeek::where('property_id', $property->id)->count();

        // The seeded in-progress week already exists at its Friday.
        $this->post(route('reports.store'), [
            'property_id' => $property->id,
            'start_date' => $this->week()->start_date->toDateString(),
        ])->assertRedirect();

        $this->assertSame($before, ReportWeek::where('property_id', $property->id)->count());
    }
}
