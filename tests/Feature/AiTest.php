<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Livewire\Sections\ActionPlan;
use App\Livewire\Sections\ActivityList;
use App\Livewire\Sections\Overview;
use App\Models\ReportWeek;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\ReportNarrator;
use App\Services\ReportData;
use App\Support\ReportInspector;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiTest extends TestCase
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

    private function fakeGroq(string $content): void
    {
        config(['services.groq.key' => 'test-key']);
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => $content]]],
            ], 200),
        ]);
    }

    public function test_overview_draft_calls_groq_and_flags_ai_draft(): void
    {
        $this->fakeGroq('October occupancy finished at 73.5%, below budget.');
        $week = $this->week();

        Livewire::test(Overview::class, ['week' => $week])
            ->call('draftWithAi', 0) // index 0 = the "financial" block
            ->assertSet('rows.0.body', 'October occupancy finished at 73.5%, below budget.')
            ->assertSet('rows.0.ai_draft', true)
            ->assertSet('aiError', null);

        // The block was persisted flagged as an AI draft (not yet human-approved).
        $this->assertDatabaseHas('overview_blocks', [
            'report_week_id' => $week->id, 'key' => 'financial', 'ai_draft' => true,
        ]);
        // The generation was recorded for audit.
        $this->assertDatabaseHas('ai_drafts', [
            'report_week_id' => $week->id, 'section' => 'overview', 'field_key' => 'financial', 'mode' => 'draft',
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ai_generated']);

        // The request went to Groq with the bearer token and model.
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/chat/completions')
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['model'] === 'llama-3.3-70b-versatile'
                && is_array($request['messages']);
        });
    }

    public function test_overview_rewrite_replaces_body(): void
    {
        $this->fakeGroq('Shorter version.');

        Livewire::test(Overview::class, ['week' => $this->week()])
            ->set('rows.0.body', 'A long paragraph that should be shortened for the report.')
            ->call('aiRewrite', 0, 'shorten')
            ->assertSet('rows.0.body', 'Shorter version.')
            ->assertSet('rows.0.ai_draft', true);

        $this->assertDatabaseHas('ai_drafts', ['section' => 'overview', 'mode' => 'shorten']);
    }

    public function test_ai_is_off_without_key_and_no_request_is_made(): void
    {
        config(['services.groq.key' => null]);
        Http::fake();

        Livewire::test(Overview::class, ['week' => $this->week()])
            ->set('rows.0.body', 'KEEP THIS')
            ->call('draftWithAi', 0)
            ->assertSet('aiError', fn ($v) => is_string($v) && str_contains($v, 'GROQ_API_KEY'))
            ->assertSet('rows.0.body', 'KEEP THIS'); // unchanged — no request happened

        Http::assertNothingSent();
    }

    public function test_activity_notes_rewrite(): void
    {
        $this->fakeGroq('Polished sales note.');

        Livewire::test(ActivityList::class, ['week' => $this->week(), 'department' => 'sales'])
            ->set('rows.0.notes', 'met agent, talk about rate')
            ->call('aiRewrite', 0, 'notes', 'rewrite')
            ->assertSet('rows.0.notes', 'Polished sales note.');

        $this->assertDatabaseHas('ai_drafts', ['section' => 'sales', 'field_key' => 'notes', 'mode' => 'rewrite']);
    }

    public function test_action_plan_remark_translate(): void
    {
        $this->fakeGroq('Rencana untuk minggu depan.');

        Livewire::test(ActionPlan::class, ['week' => $this->week()])
            ->set('rows.0.plan', 'Launch promo')
            ->set('rows.0.remark', 'Plan for next week')
            ->call('aiRewrite', 0, 'remark', 'translate_id')
            ->assertSet('rows.0.remark', 'Rencana untuk minggu depan.');

        $this->assertDatabaseHas('ai_drafts', ['section' => 'action_plan', 'field_key' => 'remark', 'mode' => 'translate_id']);
    }

    public function test_rewrite_of_empty_text_makes_no_request(): void
    {
        $this->fakeGroq('unused');

        Livewire::test(Overview::class, ['week' => $this->week()])
            ->set('rows.0.body', '')
            ->call('aiRewrite', 0, 'rewrite')
            ->assertSet('aiError', fn ($v) => is_string($v) && str_contains($v, 'Write some text'));

        Http::assertNothingSent();
    }

    public function test_facts_block_never_leaks_raw_errors_and_quotes_figures(): void
    {
        // The grounded facts block is built from ReportData and must contain the
        // real figures (so the model quotes them) and never an Excel error token.
        $facts = app(ReportNarrator::class)->facts(new ReportData($this->week()));

        $this->assertStringContainsString('Occupancy', $facts);
        $this->assertStringNotContainsString('#REF!', $facts);
        $this->assertStringNotContainsString('#DIV/0!', $facts);
    }

    public function test_anomaly_scan_runs_without_a_key(): void
    {
        config(['services.groq.key' => null]);

        $findings = ReportInspector::anomalies(new ReportData($this->week()));
        $this->assertIsArray($findings);

        Livewire::test(Overview::class, ['week' => $this->week()])
            ->call('checkAnomalies')
            ->assertSet('anomaliesChecked', true);
    }

    public function test_settings_shows_ai_card_and_saves_model(): void
    {
        $this->get(route('settings.index'))->assertOk()->assertSee('AI drafting (Groq)');

        $this->put(route('settings.ai.update'), ['groq_model' => 'llama-3.1-8b-instant'])
            ->assertRedirect();

        $this->assertSame('llama-3.1-8b-instant', Setting::get('ai', 'groq_model'));
    }
}
