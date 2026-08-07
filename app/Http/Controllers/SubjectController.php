<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Public: browse subjects (filterable list) and subject detail — no auth
 * required, matches "Public Visitor: view/search/download only".
 *
 * create()/store() are also reachable by admin/faculty/intern (see
 * routes/web.php) — any of them can add a new subject directly, no
 * approval needed. edit()/update()/destroy() are admin/intern ONLY and
 * unconditional (they can touch any subject, including ones faculty
 * created). Faculty never edits/deletes directly, even their own subjects
 * — that goes through SubjectChangeRequestController instead (hold until
 * an admin/intern approves it).
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
            'creator',
            'syllabi' => fn ($q) => $q->latest(),
        ]);

        return view('subjects.show', compact('subject'));
    }

    public function create(): View
    {
        return view('subjects.create', ['programs' => Program::orderBy('code')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSubject($request);

        $subject = Subject::create(array_merge($validated, [
            'created_by' => $request->user()->id,
        ]));

        return redirect()->route('subjects.show', $subject)->with('status', 'Nagawa ang subject.');
    }

    public function edit(Subject $subject): View
    {
        return view('subjects.edit', [
            'subject' => $subject,
            'programs' => Program::orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $subject->update($this->validateSubject($request, $subject->id));

        return redirect()->route('subjects.show', $subject)->with('status', 'Na-update ang subject.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $subject->delete();

        return redirect()->route('subjects.index')->with('status', 'Na-delete ang subject.');
    }

    private function validateSubject(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            'subject_code' => [
                'required', 'string', 'max:20',
                Rule::unique('subjects')
                    ->where(fn ($q) => $q->where('program_id', $request->input('program_id')))
                    ->ignore($ignoreId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'year_level' => ['required', 'integer', 'min:1', 'max:10'],
            'semester' => ['required', 'in:1st,2nd,summer'],
            'prerequisite' => ['nullable', 'string', 'max:255'],
            'corequisite' => ['nullable', 'string', 'max:255'],
            'lecture_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'lab_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'credited_units' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'tuition_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
        ]);
    }
}
