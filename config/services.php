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

    // "Sage" chatbot (App\Services\ChatbotService) — LEGACY, replaced by
    // Groq below as of 2026-08-13 (Gemini Flash Lite was hallucinating,
    // not following the user's language, and getting grounded Q&A wrong
    // even with matching context). Kept, not deleted, for rollback.
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
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
