<?php

namespace Tests\Feature;

use App\Models\MonthlyStat;
use App\Models\Property;
use App\Models\ReportWeek;
use App\Models\User;
use App\Support\TrendService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrendTest extends TestCase
{
    use RefreshDatabase;

    /** A property with two weeks of controlled headline figures. */
    private function propertyWithTwoWeeks(): Property
    {
        $property = Property::create([
            'code' => 'TST', 'name' => 'Test Property', 'rooms_count' => 50,
            'currency' => 'IDR', 'timezone' => 'Asia/Makassar', 'is_active' => true,
        ]);

        // Older week → September figures.
        $a = ReportWeek::create([
            'property_id' => $property->id, 'start_date' => '2026-09-18', 'end_date' => '2026-09-24',
            'year' => 2026, 'week_number' => 38, 'label' => '18 Sep – 24 Sep 2026', 'status' => 'approved',
        ]);
        MonthlyStat::create([
            'report_week_id' => $a->id, 'month' => 9, 'rn_sold' => 100,
            'occ_actual' => 0.70, 'arr_actual' => 1_000_000, 'rev_actual' => 100_000_000,
        ]);

        // Newer week → October figures (higher across the board).
        $b = ReportWeek::create([
            'property_id' => $property->id, 'start_date' => '2026-09-25', 'end_date' => '2026-10-01',
            'year' => 2026, 'week_number' => 39, 'label' => '25 Sep – 01 Oct 2026', 'status' => 'approved',
        ]);
        MonthlyStat::create([
            'report_week_id' => $b->id, 'month' => 10, 'rn_sold' => 120,
            'occ_actual' => 0.75, 'arr_actual' => 1_100_000, 'rev_actual' => 120_000_000,
        ]);

        return $property;
    }

    public function test_week_kpis_read_the_headline_month(): void
    {
        $property = $this->propertyWithTwoWeeks();
        $latest = $property->reportWeeks()->latest('start_date')->first();

        $kpis = TrendService::weekKpis($latest);

        $this->assertSame(75.0, $kpis['occ']);          // 0.75 → 75.0%
        $this->assertSame(1_100_000.0, $kpis['adr']);
        $this->assertSame(120_000_000.0, $kpis['rev']);
        $this->assertSame(120, $kpis['rn']);
    }

    public function test_comparison_computes_week_over_week_deltas(): void
    {
        $property = $this->propertyWithTwoWeeks();

        $c = TrendService::comparison($property);

        $this->assertNotNull($c);
        // Occupancy rose 70 → 75 = +5 percentage points.
        $this->assertSame('up', $c['metrics']['occ']['direction']);
        $this->assertSame(5.0, $c['metrics']['occ']['abs']);
        // Revenue rose 100M → 120M = +20%.
        $this->assertSame('up', $c['metrics']['rev']['direction']);
        $this->assertSame(20.0, $c['metrics']['rev']['pct']);
    }

    public function test_series_is_oldest_first(): void
    {
        $property = $this->propertyWithTwoWeeks();

        $series = TrendService::series($property, 8);

        $this->assertCount(2, $series);
        $this->assertSame('18 Sep – 24 Sep 2026', $series->first()['label']);
        $this->assertSame('25 Sep – 01 Oct 2026', $series->last()['label']);
    }

    public function test_portfolio_lists_every_active_property(): void
    {
        $this->seed(DatabaseSeeder::class); // BKDS, BKDU, BKV

        $portfolio = TrendService::portfolio();

        $this->assertCount(3, $portfolio);
        $this->assertEqualsCanonicalizing(
            ['BKDS', 'BKDU', 'BKV'],
            $portfolio->map(fn ($r) => $r['property']->code)->all(),
        );
    }

    public function test_trends_page_renders(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'ota@bluekarmasecrets.com')->firstOrFail());

        $this->get(route('trends.index'))
            ->assertOk()
            ->assertSee('Trends')
            ->assertSee('Portfolio')
            ->assertSee('trendData', false); // chart payload present
    }
}
