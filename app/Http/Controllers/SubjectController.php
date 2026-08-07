<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public: browse subjects (filterable list) and subject detail.
 * No auth required — matches the "Public Visitor: view/search/download only" role.
 */
class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'program' => ['nullable', 'string', 'max:20'],
            'year_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'semester' => ['nullable', 'in:1st,2nd,summer'],
        ]);

        $subjects = Subject::query()
            ->with(['program', 'latestSyllabus'])
            ->when($validated['program'] ?? null, fn ($q, $code) => $q->whereHas(
                'program',
                fn ($p) => $p->where('code', $code)
            ))
            ->when($validated['year_level'] ?? null, fn ($q, $year) => $q->where('year_level', $year))
            ->when($validated['semester'] ?? null, fn ($q, $sem) => $q->where('semester', $sem))
            ->orderBy('year_level')
            ->orderBy('subject_code')
            ->paginate(20)
            ->withQueryString();

        return view('subjects.index', [
            'subjects' => $subjects,
            'filters' => $validated,
        ]);
    }

    public function show(Subject $subject): View
    {
        $subject->load([
            'program',
            'syllabi' => fn ($q) => $q->latest(),
        ]);

        return view('subjects.show', compact('subject'));
    }
}
