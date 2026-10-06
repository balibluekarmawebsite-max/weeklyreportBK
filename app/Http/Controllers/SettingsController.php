<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Channel;
use App\Models\MarketSegment;
use App\Models\RateCode;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\GroqClient;
use App\Support\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /**
     * Fallback Groq models shown when the live /models list can't be fetched
     * (no key, or the API is unreachable). A custom .env value still works.
     */
    public const GROQ_MODELS = [
        'openai/gpt-oss-120b' => 'OpenAI GPT-OSS 120B (recommended)',
        'openai/gpt-oss-20b' => 'OpenAI GPT-OSS 20B (faster)',
        'qwen/qwen3.8-27b' => 'Qwen 3 27B',
        'allam-2-7b' => 'Allam 2 7B',
    ];

    public function index(GroqClient $groq): View
    {
        $groqModel = (string) Setting::get('ai', 'groq_model', config('services.groq.model'));

        // Ask the key which models it can actually use, so the dropdown never
        // offers a retired model ID. Fall back to the curated list on failure.
        $live = $groq->listModels();
        $modelsAreLive = $live !== [];
        $models = $modelsAreLive
            ? collect($live)->mapWithKeys(fn ($id) => [$id => $id])->all()
            : self::GROQ_MODELS;

        // Always include the active model, even if it isn't in the fetched list.
        if (! array_key_exists($groqModel, $models)) {
            $models = [$groqModel => $groqModel.($modelsAreLive ? ' (current — not in list)' : ' (from .env)')] + $models;
        }

        return view('settings.index', [
            'property' => Workspace::currentProperty(),
            'channels' => Channel::orderBy('sort_order')->get(),
            'segments' => MarketSegment::orderBy('sort_order')->get(),
            'rateCodes' => RateCode::orderBy('sort_order')->get(),
            'users' => User::with('roles')->orderBy('name')->get(),
            'roles' => Role::all(),
            'groqModel' => $groqModel,
            'groqModels' => $models,
            'modelsAreLive' => $modelsAreLive,
            'groqKeyConfigured' => $groq->configured(),
        ]);
    }

    public function updateAi(Request $request, GroqClient $groq): RedirectResponse
    {
        // Changing the global AI model is an admin-only action (also enforced by
        // the route's can:manage-settings middleware).
        Gate::authorize('manage-settings');

        // Allow any model the key can actually access (live list), plus the
        // curated fallbacks and the current .env default — never an arbitrary
        // free-form string.
        $allowed = array_values(array_unique(array_merge(
            $groq->listModels(),
            array_keys(self::GROQ_MODELS),
            [(string) config('services.groq.model')],
        )));

        $data = $request->validate([
            'groq_model' => ['required', 'string', Rule::in($allowed)],
        ]);

        Setting::put('ai', 'groq_model', $data['groq_model']);
        ActivityLog::record('updated', null, 'Set AI model to '.$data['groq_model']);

        return back()->with('status', 'ai-updated');
    }
}
