<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseChangeRequest;
use App\Models\Syllabus;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Faculty and Admin/Intern dashboards. Route-gated by the `role` middleware
 * (see routes/web.php) — faculty() only reachable by role=faculty,
 * admin() by role=admin|intern (intern acts as admin during OJT, per
 * CLAUDE.md §7).
 */
class DashboardController extends Controller
{
    public function faculty(Request $request): View
    {
        $user = $request->user();

        $courses = $user->createdCourses()
            ->with(['program', 'latestSyllabus'])
            ->orderBy('course_code')
            ->get();

        $pendingRequests = $user->courseChangeRequests()
            ->with('course')
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('dashboard.faculty', compact('courses', 'pendingRequests'));
    }

    public function admin(): View
    {
        $totalCourses = Course::count();
        $withSyllabus = Course::whereHas('syllabi')->count();
        $missing = $totalCourses - $withSyllabus;

        $recentUploads = Syllabus::with(['course', 'uploader'])
            ->latest()
            ->limit(10)
            ->get();

        $pendingRequestCount = CourseChangeRequest::where('status', 'pending')->count();

        return view('dashboard.admin', compact(
            'totalCourses',
            'withSyllabus',
            'missing',
            'recentUploads',
            'pendingRequestCount',
        ));
    }
}
