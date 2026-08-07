<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacultyAssignmentController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SyllabusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Public — search, browse, subject detail, syllabus download
|--------------------------------------------------------------------------
| No auth required. Matches the "Public Visitor" role: view/search/
| download only.
*/

// "Ask SyllabiHub" fuzzy search — public, JSON. Lives in web.php (not
// routes/api.php) since this project has no api routing group registered
// in bootstrap/app.php and doesn't use Sanctum; GET requests are exempt
// from CSRF so this is safe as-is.
Route::get('/api/search', [SearchController::class, 'search'])->name('api.search');

Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
Route::get('/subjects/{subject}', [SubjectController::class, 'show'])->name('subjects.show');

Route::get('/syllabi/{syllabus}/download', [SyllabusController::class, 'download'])->name('syllabi.download');

/*
|--------------------------------------------------------------------------
| Auth — manual login/logout (no Breeze/Fortify/Jetstream)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
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
});

/*
|--------------------------------------------------------------------------
| Admin / Intern (protected) — intern acts as admin during OJT (CLAUDE.md §7)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin,intern'])->group(function () {
    Route::get('/admin/dashboard', [DashboardController::class, 'admin'])->name('dashboard.admin');

    // Faculty-subject assignment — writes to the existing faculty_subjects
    // pivot. Scoped to assignment only; creating/editing user accounts is
    // "future" per CLAUDE.md §7, not built here.
    Route::get('/admin/faculty', [FacultyAssignmentController::class, 'index'])->name('faculty-assignments.index');
    Route::get('/admin/faculty/{faculty}', [FacultyAssignmentController::class, 'show'])->name('faculty-assignments.show');
    Route::post('/admin/faculty/{faculty}/subjects', [FacultyAssignmentController::class, 'store'])->name('faculty-assignments.store');
    Route::delete('/admin/faculty/{faculty}/subjects/{subject}', [FacultyAssignmentController::class, 'destroy'])->name('faculty-assignments.destroy');
});

/*
|--------------------------------------------------------------------------
| Syllabus upload (protected) — any authenticated staff account
|--------------------------------------------------------------------------
| Role table allows admin, faculty, and intern to upload. Faculty being
| scoped to "own subjects primarily" is a soft rule (per CLAUDE.md §7,
| not exclusive) — not enforced at the route/controller level yet. Flagging
| this rather than silently deciding it either way; revisit if it needs to
| be a hard restriction.
*/

Route::middleware(['auth', 'role:admin,faculty,intern'])->group(function () {
    Route::get('/subjects/{subject}/syllabus/upload', [SyllabusController::class, 'create'])->name('syllabi.create');
    Route::post('/subjects/{subject}/syllabus', [SyllabusController::class, 'store'])->name('syllabi.store');
});
