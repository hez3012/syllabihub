<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Course;
use App\Models\Program;
use App\Models\Syllabus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
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

        $recentUploads = Syllabus::with(['course', 'uploader'])
            ->latest()
            ->limit(10)
            ->get();

        $recentActivity = AuditTrail::latest()
            ->limit(10)
            ->get();

        return view('dashboard.admin', compact(
            'programs',
            'totalCourses',
            'withSyllabus',
            'missing',
            'missingCourses',
            'recentUploads',
            'recentActivity',
        ));
    }
}
