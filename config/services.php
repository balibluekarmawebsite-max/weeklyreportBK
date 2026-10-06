<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Groq AI (server-side only). The API key is read from .env and never
    // stored in the database; the model can be overridden per app in Settings.
    // See docs/PLAN.md section 5.
    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        // A vision-capable model, used only to read data from uploaded images
        // (screenshots). Same API key.
        'vision_model' => env('GROQ_VISION_MODEL', 'meta-llama/llama-4-scout-17b-16e-instruct'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'timeout' => (int) env('GROQ_TIMEOUT', 45),
    ],

    // VHP auto-import. The robot signs each upload with this shared secret
    // (HMAC-SHA256); requests without a valid signature are rejected. The
    // secret lives only in .env, never in the database. See docs/PLAN.md §4 & §8.
    'vhp' => [
        'import_secret' => env('VHP_IMPORT_SECRET'),
        // Max seconds a signed request may be old (replay protection).
        'timestamp_tolerance' => (int) env('VHP_IMPORT_TOLERANCE', 300),
    ],

];
