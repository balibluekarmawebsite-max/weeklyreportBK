<?php

namespace Tests\Feature;

use App\Models\ReportWeek;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'ota@bluekarmasecrets.com')->firstOrFail();
    }

    public function test_seeders_create_the_foundation_data(): void
    {
        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseHas('properties', ['code' => 'BKDS']);
        $this->assertTrue($this->admin()->isAdmin());
        $this->assertGreaterThan(0, ReportWeek::count());
    }

    public function test_guests_are_redirected_from_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_main_pages_render_for_an_authenticated_admin(): void
    {
        $this->actingAs($this->admin());

        $routes = [
            route('dashboard'),
            route('reports.index'),
            route('imports.index'),
            route('departments.index'),
            route('exports.index'),
            route('settings.index'),
        ];

        foreach ($routes as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_a_report_week_page_renders(): void
    {
        $this->actingAs($this->admin());

        $week = ReportWeek::firstOrFail();

        $this->get(route('reports.show', $week))->assertOk();
    }
}
