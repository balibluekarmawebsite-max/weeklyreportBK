<?php

namespace App\Services\Ai;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin transport over Groq's OpenAI-compatible Chat Completions API.
 *
 * It only knows how to send messages and return the assistant's text; all
 * prompt building and report grounding lives in {@see ReportNarrator}. The API
 * key is read from config (which reads .env) and is never persisted or logged.
 */
class GroqClient
{
    /** The API key is present, so AI features can run. */
    public function configured(): bool
    {
        return filled(config('services.groq.key'));
    }

    /**
     * The model to use: an app-wide Setting override ('ai'/'groq_model') if one
     * was chosen in Settings, otherwise the config/.env default.
     */
    public function model(): string
    {
        return (string) Setting::get('ai', 'groq_model', config('services.groq.model'));
    }

    /** The vision-capable model used to read data from uploaded images. */
    public function visionModel(): string
    {
        return (string) config('services.groq.vision_model');
    }

    /**
     * The chat model IDs this API key can actually access, newest first.
     *
     * Asks Groq's /models endpoint so the UI only ever offers real, available
     * models (model IDs change as Groq rotates its line-up). Returns an empty
     * array when the key is missing or the call fails — callers fall back to a
     * static list. Cached briefly to avoid a request on every settings view.
     *
     * @return array<int, string>
     */
    public function listModels(): array
    {
        if (! $this->configured()) {
            return [];
        }

        $base = rtrim((string) config('services.groq.base_url'), '/');

        return Cache::remember('groq.models', now()->addMinutes(10), function () use ($base) {
            try {
                $response = Http::baseUrl($base)
                    ->withToken((string) config('services.groq.key'))
                    ->timeout((int) config('services.groq.timeout', 45))
                    ->acceptJson()
                    ->get('/models');
            } catch (ConnectionException) {
                return [];
            }

            if ($response->failed()) {
                return [];
            }

            $ids = collect($response->json('data', []))
                ->filter(fn ($m) => is_array($m) && ! empty($m['id']))
                // Vision / audio / guard models aren't useful for text drafting.
                ->reject(fn ($m) => str_contains((string) $m['id'], 'whisper')
                    || str_contains((string) $m['id'], 'guard')
                    || str_contains((string) $m['id'], 'tts'))
                ->sortByDesc(fn ($m) => $m['created'] ?? 0)
                ->pluck('id')
                ->values()
                ->all();

            return array_map('strval', $ids);
        });
    }

    /**
     * Send a chat completion and return the assistant's trimmed text content.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options  Extra body fields (e.g. temperature).
     */
    public function chat(array $messages, array $options = []): string
    {
        if (! $this->configured()) {
            throw AiException::notConfigured();
        }

        $base = rtrim((string) config('services.groq.base_url'), '/');

        try {
            $response = Http::baseUrl($base)
                ->withToken((string) config('services.groq.key'))
                ->timeout((int) config('services.groq.timeout', 45))
                ->acceptJson()
                ->asJson()
                ->post('/chat/completions', array_merge([
                    'model' => $this->model(),
                    'messages' => $messages,
                    'temperature' => 0.3,
                ], $options));
        } catch (ConnectionException $e) {
            throw AiException::unreachable($e->getMessage());
        }

        if ($response->failed()) {
            // Surface the provider's error message when it is a plain string,
            // but never leak headers or the request body.
            $detail = $response->json('error.message');
            throw AiException::requestFailed($response->status(), is_string($detail) ? $detail : null);
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw AiException::emptyResponse();
        }

        return trim($content);
    }
}
