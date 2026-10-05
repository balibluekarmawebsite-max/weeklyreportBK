<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'ota@bluekarmasecrets.com')->firstOrFail());
    }

    public function test_dashboard_shows_kpis_charts_and_progress(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Occupancy')
            ->assertSee('Room Revenue')
            ->assertSee('Report progress')
            ->assertSee('chartData', false)       // chart data payload present
            ->assertSee('Channel Mix · this week'); // channel chart heading
    }

    public function test_dashboard_marks_sections_done_for_seeded_week(): void
    {
        // The seeded BKDS week has data in every section, so progress is 11/11.
        $this->get(route('dashboard'))->assertSee('11/11');
    }
}
