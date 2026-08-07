<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin/intern tool for assigning subjects to faculty accounts, via the
 * existing faculty_subjects pivot (no schema change — just reads/writes
 * to a table that already exists). Scoped narrowly to assignment only;
 * creating/editing user accounts is explicitly "future" per CLAUDE.md §7
 * and out of scope here.
 */
class FacultyAssignmentController extends Controller
{
    public function index(): View
    {
        $faculty = User::query()
            ->where('role', 'faculty')
            ->withCount('subjects')
            ->orderBy('name')
            ->get();

        return view('admin.faculty-assignments.index', compact('faculty'));
    }

    public function show(User $faculty): View
    {
        abort_unless($faculty->role === 'faculty', 404);

        $faculty->load(['subjects.program']);
        $assignedIds = $faculty->subjects->pluck('id');

        $availableSubjects = Subject::query()
            ->with('program')
            ->whereNotIn('id', $assignedIds)
            ->orderBy('subject_code')
            ->get();

        return view('admin.faculty-assignments.show', compact('faculty', 'availableSubjects'));
    }

    public function store(Request $request, User $faculty): RedirectResponse
    {
        abort_unless($faculty->role === 'faculty', 404);

        $validated = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
        ]);

        $faculty->subjects()->syncWithoutDetaching([$validated['subject_id']]);

        return back()->with('status', 'Na-assign ang subject.');
    }

    public function destroy(User $faculty, Subject $subject): RedirectResponse
    {
        abort_unless($faculty->role === 'faculty', 404);

        $faculty->subjects()->detach($subject->id);

        return back()->with('status', 'Na-remove ang assignment.');
    }
}
