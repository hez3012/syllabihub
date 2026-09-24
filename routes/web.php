<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SyllabusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes — browse courses, view syllabi, download/preview files
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => view('welcome'))->name('home');

Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
Route::get('/courses/{course}/panel', [CourseController::class, 'panel'])->name('courses.panel');
Route::get('/syllabi/{syllabus}/download', [SyllabusController::class, 'download'])->name('syllabi.download');
Route::get('/syllabi/{syllabus}/preview', [SyllabusController::class, 'preview'])->name('syllabi.preview');

/*
|--------------------------------------------------------------------------
| Public API — search & chatbot (no auth required)
|--------------------------------------------------------------------------
*/
Route::get('/api/search', [SearchController::class, 'search'])->name('api.search');
Route::post('/api/chat', [ChatbotController::class, 'chat'])
    ->middleware(['throttle:groq-per-minute', 'throttle:groq-per-day'])
    ->name('api.chat');

/*
|--------------------------------------------------------------------------
| Admin Login — secret URL, guest-only
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/admin-login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/admin-login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::post('/admin-logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Admin-only Routes — requires auth + admin role
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard.admin');

    // Course CRUD
    Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');
    Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
    Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
    Route::put('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
    Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

    // Syllabus upload
    Route::get('/courses/{course}/syllabus/upload', [SyllabusController::class, 'create'])->name('syllabi.create');
    Route::post('/courses/{course}/syllabus', [SyllabusController::class, 'store'])->name('syllabi.store');

    // Audit logs & trails
    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
});
