<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Project uses Bootstrap 5.3, not Tailwind — keep pagination()
        // links() output consistent instead of Laravel's Tailwind default.
        Paginator::useBootstrapFive();

        // Legacy Gemini rate limits — commented out, kept for potential
        // rollback. Gemini free tier was 15 RPM / 1,500 RPD FOR THE WHOLE
        // API KEY, shared across every user of the app, not per account.
        // RateLimiter::for('gemini-per-minute', fn () => Limit::perMinute(12)->by('global'));
        // RateLimiter::for('gemini-per-day', fn () => Limit::perDay(1400)->by('global'));

        // "Sage" chatbot (routes/web.php's /api/chat) — switched from
        // Gemini to Groq 2026-08-13 (see ChatbotService docblock). Groq
        // free tier for llama-3.3-70b-versatile is 30 RPM / 14,400 RPD
        // FOR THE WHOLE API KEY, shared across every user of the app, not
        // per account. A normal `throttle:N,1` keys by user/IP, which
        // would let N different users each burn N requests at once —
        // nowhere close to what actually protects the shared quota.
        // `by('global')` below keys every request to the same bucket
        // regardless of who's asking, so the limit is enforced the way
        // Groq actually enforces it. Kept a safety margin under both
        // real caps.
        RateLimiter::for('groq-per-minute', fn () => Limit::perMinute(25)->by('global'));
        RateLimiter::for('groq-per-day', fn () => Limit::perDay(14000)->by('global'));
    }
}
