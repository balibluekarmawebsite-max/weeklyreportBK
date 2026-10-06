<?php

namespace Tests\Feature;

use App\Models\ReportWeek;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportWeekDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function week(): ReportWeek
    {
        return ReportWeek::has('monthlyStats')->firstOrFail();
    }

    public function test_admin_can_delete_with_matching_confirmation(): void
    {
        $this->actingAs(User::where('email', 'ota@bluekarmasecrets.com')->firstOrFail());
        $week = $this->week();

        $this->delete(route('reports.destroy', $week), ['confirm' => $week->label])
            ->assertRedirect(route('reports.index'));

        $this->assertDatabaseMissing('report_weeks', ['id' => $week->id]);
        $this->assertSame(0, $week->monthlyStats()->count()); // section rows cascaded
    }

    public function test_wrong_confirmation_is_rejected(): void
    {
        $this->actingAs(User::where('email', 'ota@bluekarmasecrets.com')->firstOrFail());
        $week = $this->week();

        $this->delete(route('reports.destroy', $week), ['confirm' => 'nope'])
            ->assertSessionHasErrors('confirm');

        $this->assertDatabaseHas('report_weeks', ['id' => $week->id]);
    }

    public function test_non_admin_cannot_delete(): void
    {
        $this->actingAs(User::factory()->create());
        $week = $this->week();

        $this->delete(route('reports.destroy', $week), ['confirm' => $week->label])
            ->assertForbidden();

        $this->assertDatabaseHas('report_weeks', ['id' => $week->id]);
    }
}
