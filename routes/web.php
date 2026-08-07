<?php

use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// "Ask SyllabiHub" fuzzy search — public, JSON. Lives in web.php (not
// routes/api.php) since this project has no api routing group registered
// in bootstrap/app.php and doesn't use Sanctum; GET requests are exempt
// from CSRF so this is safe as-is.
Route::get('/api/search', [SearchController::class, 'search'])->name('api.search');
