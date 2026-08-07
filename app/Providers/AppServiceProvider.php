<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
    }
}
