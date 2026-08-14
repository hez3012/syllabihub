<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    // "Sage" chatbot (App\Services\ChatbotService) — Groq's automatic
    // FALLBACK as of 2026-08-13, per Rico (see ChatbotService::
    // tryGeminiFallback()), not the primary model — Groq is. Originally
    // WAS the primary model until 2026-08-13 (Gemini Flash Lite was
    // hallucinating, not following the user's language, and getting
    // grounded Q&A wrong even with matching context), which is also why
    // Rico didn't want that Lite tier again for this fallback role — see
    // .env's own comment for which model names are actually still live
    // on this account.
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
    ],

    // "Sage" chatbot (App\Services\ChatbotService) — Groq, free tier,
    // per Rico 2026-08-13. OpenAI-compatible REST API (no SDK). Get a key
    // at console.groq.com/keys. Double-check GROQ_MODEL is still current
    // there before going live; provider model names/versions change over
    // time and this default may age out too.
    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
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

];
