<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CourseChangeRequestController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacultyAccountController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SyllabusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Homepage is the frontend team's to design (CLAUDE.md §8) — welcome.blade.php
// removed, redirecting to Browse Courses as a functional placeholder.
Route::get('/', fn () => redirect()->route('courses.index'));

/*
|--------------------------------------------------------------------------
| Search, browse, course detail, syllabus download/preview (protected)
|--------------------------------------------------------------------------
| All require login — no public/guest access anywhere except the login
| page itself (CLAUDE.md §7). Any authenticated role (admin/faculty/
| intern) may view/search/download.
*/

Route::middleware(['auth', 'role:admin,faculty,intern'])->group(function () {
    // "Ask SyllabiHub" fuzzy search — JSON. Lives in web.php (not
    // routes/api.php) since this project has no api routing group
    // registered in bootstrap/app.php and doesn't use Sanctum.
    Route::get('/api/search', [SearchController::class, 'search'])->name('api.search');

    // "Sage" chatbot (App\Services\ChatbotService) — same
    // auth gate as search above, plus two GLOBAL (not per-user) throttles
    // registered in AppServiceProvider — Groq's free-tier 30 RPM /
    // 14,400 RPD quota (llama-3.3-70b-versatile) belongs to the whole API
    // key, shared across every user, so a per-user throttle alone
    // wouldn't actually protect it. (Switched from Gemini 2026-08-13 —
    // rollback here means reverting this line + AppServiceProvider's
    // limiter names, the old gemini-per-minute/-day limiters are kept
    // commented out there for that.)
    Route::post('/api/chat', [ChatbotController::class, 'chat'])
        ->middleware(['throttle:groq-per-minute', 'throttle:groq-per-day'])
        ->name('api.chat');

    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');

    // NOTE: literal "/courses/create" MUST be registered before the
    // "/courses/{course}" wildcard below — Laravel matches routes in
    // registration order, so if the wildcard came first it would swallow
    // "create" as a {course} id and 404 (no Course with id="create").
    Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');
    Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');

    Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');

    Route::get('/syllabi/{syllabus}/download', [SyllabusController::class, 'download'])->name('syllabi.download');
    Route::get('/syllabi/{syllabus}/preview', [SyllabusController::class, 'preview'])->name('syllabi.preview');
});

/*
|--------------------------------------------------------------------------
| Auth — manual login/logout/password-reset (no Breeze/Fortify/Jetstream)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

    // Route names below match Laravel's convention exactly — the built-in
    // ResetPassword notification (fired by Password::sendResetLink()) looks
    // up route('password.reset', ...) to build the email link, so this name
    // can't be changed without also overriding that notification.
    Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Post-login landing — sends the user to the dashboard for their role.
Route::get('/dashboard', function (Request $request) {
    $user = $request->user();

    return match (true) {
        $user->isFaculty() => redirect()->route('dashboard.faculty'),
        $user->isAdmin(), $user->isIntern() => redirect()->route('dashboard.admin'),
        default => redirect('/'),
    };
})->middleware('auth')->name('dashboard.redirect');

/*
|--------------------------------------------------------------------------
| Faculty (protected)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:faculty'])->group(function () {
    Route::get('/faculty/dashboard', [DashboardController::class, 'faculty'])->name('dashboard.faculty');

    // Faculty never edits/deletes a course directly, even one they
    // created — these submit a pending request instead (hold until an
    // admin/intern approves it). See CourseChangeRequestController.
    Route::get('/courses/{course}/request-edit', [CourseChangeRequestController::class, 'editForm'])->name('course-requests.edit-form');
    Route::post('/courses/{course}/request-update', [CourseChangeRequestController::class, 'requestUpdate'])->name('course-requests.update');
    Route::post('/courses/{course}/request-delete', [CourseChangeRequestController::class, 'requestDelete'])->name('course-requests.delete');
});

/*
|--------------------------------------------------------------------------
| Admin / Intern (protected) — intern acts as admin during OJT (CLAUDE.md §7)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin,intern'])->group(function () {
    Route::get('/admin/dashboard', [DashboardController::class, 'admin'])->name('dashboard.admin');

    // Direct course edit/delete — unconditional, no ownership check
    // (unlike faculty's request-* routes above).
    Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
    Route::put('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
    Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

    // Faculty edit/delete request queue.
    Route::get('/admin/course-requests', [CourseChangeRequestController::class, 'index'])->name('course-requests.index');
    Route::post('/admin/course-requests/{changeRequest}/approve', [CourseChangeRequestController::class, 'approve'])->name('course-requests.approve');
    Route::post('/admin/course-requests/{changeRequest}/reject', [CourseChangeRequestController::class, 'reject'])->name('course-requests.reject');

    // Faculty account creation — replaces the old "assign courses to
    // faculty" feature. Actual credential delivery is a manual step
    // outside the app (dev team emails it).
    Route::get('/admin/faculty', [FacultyAccountController::class, 'index'])->name('faculty-accounts.index');
    Route::get('/admin/faculty/create', [FacultyAccountController::class, 'create'])->name('faculty-accounts.create');
    Route::post('/admin/faculty', [FacultyAccountController::class, 'store'])->name('faculty-accounts.store');
});

/*
|--------------------------------------------------------------------------
| Syllabus upload (protected) — any authenticated staff account
|--------------------------------------------------------------------------
| Role table allows admin, faculty, and intern to upload. Faculty being
| scoped to "own courses primarily" is a soft rule (per CLAUDE.md §7,
| not exclusive) — not enforced at the route/controller level. Flagging
| this rather than silently deciding it either way; revisit if it needs to
| be a hard restriction.
*/

Route::middleware(['auth', 'role:admin,faculty,intern'])->group(function () {
    Route::get('/courses/{course}/syllabus/upload', [SyllabusController::class, 'create'])->name('syllabi.create');
    Route::post('/courses/{course}/syllabus', [SyllabusController::class, 'store'])->name('syllabi.store');
});
