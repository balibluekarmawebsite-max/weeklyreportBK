<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Channel;
use App\Models\MarketSegment;
use App\Models\RateCode;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /** Curated Groq models offered in the UI (a custom .env value still works). */
    public const GROQ_MODELS = [
        'llama-3.3-70b-versatile' => 'Llama 3.3 70B — versatile (recommended)',
        'llama-3.1-8b-instant' => 'Llama 3.1 8B — instant (fast, low cost)',
        'openai/gpt-oss-120b' => 'GPT-OSS 120B',
        'openai/gpt-oss-20b' => 'GPT-OSS 20B',
    ];

    public function index(): View
    {
        $groqModel = (string) Setting::get('ai', 'groq_model', config('services.groq.model'));

        // Always include the active model in the list, even if it was set via
        // .env and isn't one of the curated options.
        $models = self::GROQ_MODELS;
        if (! array_key_exists($groqModel, $models)) {
            $models = [$groqModel => $groqModel.' (from .env)'] + $models;
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
            'groqKeyConfigured' => filled(config('services.groq.key')),
        ]);
    }

    public function updateAi(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'groq_model' => ['required', 'string', 'max:100'],
        ]);

        Setting::put('ai', 'groq_model', $data['groq_model']);
        ActivityLog::record('updated', null, 'Set AI model to '.$data['groq_model']);

        return back()->with('status', 'ai-updated');
    }
}
