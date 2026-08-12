<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Subject;
use App\Services\SyllabusFileService;
use Illuminate\Database\QueryException;
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
 *
 * store()/update() also accept the same optional file_pdf/file_docx (+
 * curriculum_year) fields SyllabusController's standalone upload page
 * does — added per Rico, 2026-08-12, so a syllabus can be attached right
 * on the Add/Edit Subject form instead of a separate navigation. Both
 * delegate to SyllabusFileService, which is also where the "replace,
 * don't append" behavior lives.
 */
class SubjectController extends Controller
{
    public function __construct(private readonly SyllabusFileService $files)
    {
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'program' => ['nullable', 'string', 'max:20'],
            'year_level' => ['nullable', 'integer', 'min:1', 'max:4'],
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
        return view('subjects.create', [
            'programs' => Program::orderBy('code')->get(),
            'curriculumYears' => SyllabusController::curriculumYearOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSubject($request);
        $fileValidated = $this->validateFiles($request);

        try {
            $subject = Subject::create(array_merge($validated, [
                'created_by' => $request->user()->id,
            ]));
        } catch (QueryException $e) {
            // Backstop for a race between two simultaneous submits — the
            // Rule::unique checks above already catch this in the normal
            // case, but they can't see a row inserted after they ran.
            return back()
                ->withErrors(['subject_code' => 'That subject code or title was just taken by another submission. Please check and try again.'])
                ->withInput();
        }

        if (SyllabusFileService::hasAnyFile($fileValidated)) {
            $this->files->storeFor(
                $subject,
                $fileValidated,
                $request->user()->id,
                $fileValidated['curriculum_year'] ?? null
            );
        }

        return redirect()->route('subjects.show', $subject)->with('status', 'Subject created successfully.');
    }

    public function edit(Subject $subject): View
    {
        $subject->load(['syllabi' => fn ($q) => $q->latest()]);

        return view('subjects.edit', [
            'subject' => $subject,
            'programs' => Program::orderBy('code')->get(),
            'curriculumYears' => SyllabusController::curriculumYearOptions(),
        ]);
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $validated = $this->validateSubject($request, $subject->id);
        $fileValidated = $this->validateFiles($request);

        try {
            $subject->update($validated);
        } catch (QueryException $e) {
            // Same race backstop as store() above.
            return back()
                ->withErrors(['subject_code' => 'That subject code or title was just taken by another submission. Please check and try again.'])
                ->withInput();
        }

        if (SyllabusFileService::hasAnyFile($fileValidated)) {
            $this->files->storeFor(
                $subject,
                $fileValidated,
                $request->user()->id,
                $fileValidated['curriculum_year'] ?? null
            );
        }

        return redirect()->route('subjects.show', $subject)->with('status', 'Subject updated successfully.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $subject->delete();

        return redirect()->route('subjects.index')->with('status', 'Subject deleted successfully.');
    }

    private function validateSubject(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            // System-wide uniqueness (not scoped to program_id) per Rico,
            // 2026-08-12: only one live subject may ever hold a given code
            // or title, across BSIT/DIT both — a curriculum update is
            // modeled as admin deleting the old subject, not coexisting
            // side-by-side with it. subjects.title/subject_code columns
            // use utf8mb4_0900_ai_ci collation, so these unique checks are
            // already case-insensitive at the DB level. whereNull(deleted_at)
            // excludes soft-deleted subjects so a retired code/title frees
            // up for reuse.
            'subject_code' => [
                'required', 'string', 'max:20',
                Rule::unique('subjects', 'subject_code')->whereNull('deleted_at')->ignore($ignoreId),
            ],
            'title' => [
                'required', 'string', 'max:255',
                Rule::unique('subjects', 'title')->whereNull('deleted_at')->ignore($ignoreId),
            ],
            // Year level is a dropdown of 1st-4th Year only (CLAUDE.md
            // programs are 4-year curricula) — Rico, 2026-08-12.
            'year_level' => ['required', 'integer', 'min:1', 'max:4'],
            'semester' => ['required', 'in:1st,2nd,summer'],
            // Already optional by design — prerequisite/corequisite are
            // legitimately not every subject's business.
            'prerequisite' => ['nullable', 'string', 'max:255'],
            'corequisite' => ['nullable', 'string', 'max:255'],
            'lecture_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'lab_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'credited_units' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'tuition_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
        ]);
    }

    /** Optional inline syllabus upload/replace fields shared with SyllabusController's standalone upload page. */
    private function validateFiles(Request $request): array
    {
        return $request->validate(array_merge(
            SyllabusFileService::validationRules(),
            ['curriculum_year' => SyllabusFileService::curriculumYearRule()]
        ));
    }
}
