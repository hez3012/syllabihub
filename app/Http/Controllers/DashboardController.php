<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\SubjectChangeRequest;
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

        $subjects = $user->createdSubjects()
            ->with(['program', 'latestSyllabus'])
            ->orderBy('subject_code')
            ->get();

        $pendingRequests = $user->subjectChangeRequests()
            ->with('subject')
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('dashboard.faculty', compact('subjects', 'pendingRequests'));
    }

    public function admin(): View
    {
        $totalSubjects = Subject::count();
        $withSyllabus = Subject::whereHas('syllabi')->count();
        $missing = $totalSubjects - $withSyllabus;

        $recentUploads = Syllabus::with(['subject', 'uploader'])
            ->latest()
            ->limit(10)
            ->get();

        $pendingRequestCount = SubjectChangeRequest::where('status', 'pending')->count();

        return view('dashboard.admin', compact(
            'totalSubjects',
            'withSyllabus',
            'missing',
            'recentUploads',
            'pendingRequestCount',
        ));
    }
}
