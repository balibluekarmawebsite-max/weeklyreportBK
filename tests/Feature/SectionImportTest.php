<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Livewire\SectionImport;
use App\Models\MonthlyStat;
use App\Models\Property;
use App\Models\ReportWeek;
use App\Models\SocialMediaMetric;
use App\Models\User;
use App\Services\Ai\SectionExtractor;
use App\Services\WeeklyReportImporter;
use App\Support\Workspace;
use Database\Seeders\PropertySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SectionImportTest extends TestCase
{
    use RefreshDatabase;

    private ReportWeek $week;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PropertySeeder::class);
        $this->actingAs(User::factory()->create());

        $bkv = Property::where('code', 'BKV')->firstOrFail();
        Workspace::setProperty($bkv->id);
        $this->week = ReportWeek::create([
            'property_id' => $bkv->id, 'start_date' => '2026-09-25', 'end_date' => '2026-10-01',
            'year' => 2026, 'week_number' => 39, 'label' => '25 Sep – 01 Oct 2026', 'status' => ReportStatus::InProgress,
        ]);
    }

    private function fakeGroq(array $rows): void
    {
        config(['services.groq.key' => 'test-key']);
        Http::fake(['api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['rows' => $rows])]]],
        ], 200)]);
    }

    public function test_apply_section_replaces_only_that_section(): void
    {
        MonthlyStat::create(['report_week_id' => $this->week->id, 'month' => 10, 'rn_sold' => 361, 'occ_actual' => 0.7763]);
        SocialMediaMetric::create(['report_week_id' => $this->week->id, 'metric_key' => 'Followers', 'last_week' => 1, 'this_week' => 2, 'sort_order' => 0]);

        $count = (new WeeklyReportImporter)->applySection('social', [
            ['metric' => 'Followers', 'last_week' => 100, 'this_week' => 120],
            ['metric' => 'Impression', 'last_week' => 5000, 'this_week' => 6000],
        ], $this->week);

        $this->assertSame(2, $count);
        $this->assertSame(2, $this->week->socialMediaMetrics()->count());          // replaced
        $this->assertSame(120, (int) $this->week->socialMediaMetrics()->where('metric_key', 'Followers')->value('this_week'));
        $this->assertSame(1, $this->week->monthlyStats()->count());                 // untouched
        $this->assertSame(361, (int) $this->week->monthlyStats()->first()->rn_sold);
    }

    public function test_ai_extract_from_csv_text(): void
    {
        $this->fakeGroq([['metric' => 'Followers', 'last_week' => '1,200', 'this_week' => '1,350']]);

        $rows = app(SectionExtractor::class)->extract('social', $this->week, "Followers,1200,1350\n", []);

        $this->assertCount(1, $rows);
        $this->assertSame('Followers', $rows[0]['metric']);
        $this->assertSame(1350, $rows[0]['this_week']); // commas stripped, cast to int

        Http::assertSent(fn ($r) => $r['model'] === config('services.groq.model') && str_contains($r->url(), '/chat/completions'));
    }

    public function test_ai_extract_from_image_uses_vision_model(): void
    {
        $this->fakeGroq([['metric' => 'Account Reached', 'last_week' => 900, 'this_week' => 1100]]);
        $png = tempnam(sys_get_temp_dir(), 'img_').'.png';
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        $rows = app(SectionExtractor::class)->extract('social', $this->week, null, [$png]);

        $this->assertSame(1100, $rows[0]['this_week']);
        Http::assertSent(function ($r) {
            $content = $r['messages'][1]['content'];

            return $r['model'] === config('services.groq.vision_model')
                && is_array($content)
                && collect($content)->contains(fn ($p) => ($p['type'] ?? '') === 'image_url');
        });
        @unlink($png);
    }

    public function test_livewire_social_ai_import_applies_only_social(): void
    {
        MonthlyStat::create(['report_week_id' => $this->week->id, 'month' => 10, 'rn_sold' => 361]);
        $this->fakeGroq([['metric' => 'Followers', 'last_week' => 1000, 'this_week' => 1100]]);

        Livewire::test(SectionImport::class)
            ->set('weekId', $this->week->id)
            ->call('openSection', 'social')
            ->set('file', UploadedFile::fake()->createWithContent('insights.csv', "Followers,1000,1100\n"))
            ->assertSet('previewing', true)
            ->assertSet('aiDraft', true)
            ->call('apply')
            ->assertSet('notice', fn ($v) => is_string($v) && str_contains($v, 'applied'));

        $this->assertSame(1, $this->week->socialMediaMetrics()->count());
        $this->assertSame(1100, (int) $this->week->socialMediaMetrics()->first()->this_week);
        $this->assertSame(1, $this->week->monthlyStats()->count()); // untouched
        $this->assertDatabaseHas('imports', ['report_week_id' => $this->week->id, 'status' => 'applied']);
    }

    public function test_no_key_shows_hint_for_ai_section(): void
    {
        config(['services.groq.key' => null]);

        Livewire::test(SectionImport::class)
            ->set('weekId', $this->week->id)
            ->call('openSection', 'social')
            ->set('file', UploadedFile::fake()->createWithContent('insights.csv', "Followers,1,2\n"))
            ->assertSet('previewing', false)
            ->assertSet('errorMessage', fn ($v) => is_string($v) && str_contains($v, 'GROQ_API_KEY'));

        Http::assertNothingSent();
    }
}
