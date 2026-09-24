<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Course;
use App\Models\Program;
use App\Services\SyllabusFileService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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

        $this->recordTrail($request, 'created', $course, null, $course->toArray());

        return redirect()->route('courses.index')->with('status', 'Course created successfully.');
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

        $oldValues = $course->toArray();

        try {
            $course->update($validated);
        } catch (QueryException $e) {
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

        $newValues = $course->fresh()->toArray();
        $changed = $this->diffValues($oldValues, $newValues);
        $this->recordTrail($request, 'updated', $course, $changed['old'] ?? null, $changed['new'] ?? null);

        return redirect()->route('courses.index')->with('status', 'Course updated successfully.');
    }

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $oldValues = $course->toArray();
        $course->delete();

        $this->recordTrail($request, 'deleted', $course, $oldValues, null);

        return redirect()->route('courses.index')->with('status', 'Course deleted successfully.');
    }

    private function recordTrail(Request $request, string $action, Course $course, ?array $oldValues, ?array $newValues): void
    {
        $user = $request->user();

        AuditTrail::create([
            'full_name' => $user->name,
            'email' => $user->email,
            'action' => $action,
            'subject_type' => Course::class,
            'subject_id' => $course->id,
            'description' => ucfirst($action) . " course: {$course->course_code} — {$course->title}",
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'created_at' => now(),
        ]);
    }

    private function diffValues(array $old, array $new): array
    {
        $ignored = ['updated_at', 'created_at', 'deleted_at'];
        $oldOut = [];
        $newOut = [];

        foreach ($new as $key => $value) {
            if (in_array($key, $ignored)) continue;
            if (!array_key_exists($key, $old)) continue;
            if ($old[$key] != $value) {
                $oldOut[$key] = $old[$key];
                $newOut[$key] = $value;
            }
        }

        return ['old' => $oldOut ?: null, 'new' => $newOut ?: null];
    }

    private function validateCourse(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            'course_code' => [
                'required', 'string', 'max:20',
                Rule::unique('courses', 'course_code')->whereNull('deleted_at')->ignore($ignoreId),
            ],
            'title' => [
                'required', 'string', 'max:255',
                Rule::unique('courses', 'title')->whereNull('deleted_at')->ignore($ignoreId),
            ],
            'year_level' => ['required', 'integer', 'min:1', 'max:4'],
            'semester' => ['required', 'in:1st,2nd,summer'],
            'prerequisite' => ['nullable', 'string', 'max:255'],
            'corequisite' => ['nullable', 'string', 'max:255'],
            'lecture_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'lab_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'credited_units' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'tuition_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
        ]);
    }

    private function validateFiles(Request $request): array
    {
        return $request->validate(array_merge(
            SyllabusFileService::validationRules(),
            ['curriculum_year' => SyllabusFileService::curriculumYearRule()]
        ));
    }
}
