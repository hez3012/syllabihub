<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseChangeRequest;
use App\Models\Program;
use App\Models\Syllabus;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
        $programs = Program::withCount([
            'courses as total_courses',
            'courses as with_syllabus_count' => function ($q) {
                $q->whereHas('syllabi');
            },
        ])->get();

        $totalCourses = Course::count();
        $withSyllabus = Course::whereHas('syllabi')->count();
        $missing = $totalCourses - $withSyllabus;

        $missingCourses = Course::with(['program'])
            ->whereDoesntHave('syllabi')
            ->orderBy('course_code')
            ->limit(10)
            ->get();

        $pendingRequests = CourseChangeRequest::with(['course', 'requester'])
            ->where('status', 'pending')
            ->latest()
            ->limit(10)
            ->get();

        $pendingRequestCount = $pendingRequests->count();

        $recentUploads = Syllabus::with(['course', 'uploader'])
            ->latest()
            ->limit(10)
            ->get();

        return view('dashboard.admin', compact(
            'programs',
            'totalCourses',
            'withSyllabus',
            'missing',
            'missingCourses',
            'pendingRequests',
            'pendingRequestCount',
            'recentUploads',
        ));
    }
}
