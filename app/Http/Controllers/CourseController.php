<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Program;
use App\Services\SyllabusFileService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Public: browse courses (filterable list) and course detail — no auth
 * required, matches "Public Visitor: view/search/download only".
 *
 * create()/store() are also reachable by admin/faculty/intern (see
 * routes/web.php) — any of them can add a new course directly, no
 * approval needed. edit()/update()/destroy() are admin/intern ONLY and
 * unconditional (they can touch any course, including ones faculty
 * created). Faculty never edits/deletes directly, even their own courses
 * — that goes through CourseChangeRequestController instead (hold until
 * an admin/intern approves it).
 *
 * store()/update() also accept the same optional file_pdf/file_docx (+
 * curriculum_year) fields SyllabusController's standalone upload page
 * does — added per Rico, 2026-08-12, so a syllabus can be attached right
 * on the Add/Edit Course form instead of a separate navigation. Both
 * delegate to SyllabusFileService, which is also where the "replace,
 * don't append" behavior lives.
 */
class CourseController extends Controller
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

    $baseQuery = Course::query()
        ->when($validated['program'] ?? null, fn ($q, $code) => $q->whereHas(
            'program',
            fn ($p) => $p->where('code', $code)
        ))
        ->when($validated['year_level'] ?? null, fn ($q, $year) => $q->where('year_level', $year))
        ->when($validated['semester'] ?? null, fn ($q, $sem) => $q->where('semester', $sem));

    // True count across ALL matching courses (not just the current page) —
    // used for the "with syllabus" stat on the Browse Courses page.
    $withSyllabusCount = (clone $baseQuery)->whereHas('syllabi')->count();

    $courses = (clone $baseQuery)
        ->with(['program', 'latestSyllabus'])
        ->orderBy('year_level')
        ->orderBy('course_code')
        ->paginate(20)
        ->withQueryString();

    return view('courses.index', [
        'courses' => $courses,
        'filters' => $validated,
        'withSyllabusCount' => $withSyllabusCount,
    ]);
}

    public function show(Course $course): View
    {
        $course->load([
            'program',
            'creator',
            'syllabi' => fn ($q) => $q->latest(),
        ]);

        return view('courses.show', compact('course'));
    }

    /**
     * Returns the subject detail as a partial HTML fragment
     * for the slide-in panel (fetched via JS, no full page load).
     */
    public function panel(Course $course)
    {
        $course->load([
            'program',
            'creator',
            'latestSyllabus',
            'syllabi' => fn ($q) => $q->latest(),
        ]);

        return view('courses._panel', compact('course'));
    }

    public function create(): View
    {
        return view('courses.create', [
            'programs' => Program::orderBy('code')->get(),
            'curriculumYears' => SyllabusController::curriculumYearOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCourse($request);
        $fileValidated = $this->validateFiles($request);

        try {
            $course = Course::create(array_merge($validated, [
                'created_by' => $request->user()->id,
            ]));
        } catch (QueryException $e) {
            // Backstop for a race between two simultaneous submits — the
            // Rule::unique checks above already catch this in the normal
            // case, but they can't see a row inserted after they ran.
            return back()
                ->withErrors(['course_code' => 'That course code or title was just taken by another submission. Please check and try again.'])
                ->withInput();
        }

        if (SyllabusFileService::hasAnyFile($fileValidated)) {
            $this->files->storeFor(
                $course,
                $fileValidated,
                $request->user()->id,
                $fileValidated['curriculum_year'] ?? null
            );
        }

        return redirect()->route('courses.show', $course)->with('status', 'Course created successfully.');
    }

    public function edit(Course $course): View
    {
        $course->load(['syllabi' => fn ($q) => $q->latest()]);

        return view('courses.edit', [
            'course' => $course,
            'programs' => Program::orderBy('code')->get(),
            'curriculumYears' => SyllabusController::curriculumYearOptions(),
        ]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $validated = $this->validateCourse($request, $course->id);
        $fileValidated = $this->validateFiles($request);

        try {
            $course->update($validated);
        } catch (QueryException $e) {
            // Same race backstop as store() above.
            return back()
                ->withErrors(['course_code' => 'That course code or title was just taken by another submission. Please check and try again.'])
                ->withInput();
        }

        if (SyllabusFileService::hasAnyFile($fileValidated)) {
            $this->files->storeFor(
                $course,
                $fileValidated,
                $request->user()->id,
                $fileValidated['curriculum_year'] ?? null
            );
        }

        return redirect()->route('courses.show', $course)->with('status', 'Course updated successfully.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $course->delete();

        return redirect()->route('courses.index')->with('status', 'Course deleted successfully.');
    }

    private function validateCourse(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            // System-wide uniqueness (not scoped to program_id) per Rico,
            // 2026-08-12: only one live course may ever hold a given code
            // or title, across BSIT/DIT both — a curriculum update is
            // modeled as admin deleting the old course, not coexisting
            // side-by-side with it. courses.title/course_code columns
            // use utf8mb4_0900_ai_ci collation, so these unique checks are
            // already case-insensitive at the DB level. whereNull(deleted_at)
            // excludes soft-deleted courses so a retired code/title frees
            // up for reuse.
            'course_code' => [
                'required', 'string', 'max:20',
                Rule::unique('courses', 'course_code')->whereNull('deleted_at')->ignore($ignoreId),
            ],
            'title' => [
                'required', 'string', 'max:255',
                Rule::unique('courses', 'title')->whereNull('deleted_at')->ignore($ignoreId),
            ],
            // Year level is a dropdown of 1st-4th Year only (CLAUDE.md
            // programs are 4-year curricula) — Rico, 2026-08-12.
            'year_level' => ['required', 'integer', 'min:1', 'max:4'],
            'semester' => ['required', 'in:1st,2nd,summer'],
            // Already optional by design — prerequisite/corequisite are
            // legitimately not every course's business.
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
