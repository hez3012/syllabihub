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

        $programCode = $validated['program'] ?? null;
        $yearLevel = $validated['year_level'] ?? null;
        $semester = $validated['semester'] ?? null;

        $baseQuery = Course::query()
            ->when($programCode || $yearLevel || $semester, fn ($q) => $q->whereHas(
                'programs',
                fn ($p) => $p
                    ->when($programCode, fn ($x) => $x->where('programs.code', $programCode))
                    ->when($yearLevel, fn ($x) => $x->where('course_program.year_level', $yearLevel))
                    ->when($semester, fn ($x) => $x->where('course_program.semester', $semester))
            ));

        $withSyllabusCount = (clone $baseQuery)->whereHas('syllabi')->count();

        $courses = (clone $baseQuery)
            ->with(['programs', 'latestSyllabus'])
            ->orderByRaw('(SELECT MIN(year_level) FROM course_program WHERE course_program.course_id = courses.id)')
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
            'programs',
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
        $placements = $validated['placements'];
        unset($validated['placements']);
        $fileValidated = $this->validateFiles($request);

        try {
            $course = Course::create(array_merge($validated, [
                'created_by' => $request->user()->id,
            ]));
            $course->programs()->sync($placements);
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

        $this->recordTrail($request, 'created', $course, null, array_merge(
            $course->toArray(),
            ['programs' => $this->programSnapshot($course)]
        ));

        return redirect()->route('courses.index')->with('status', 'Course created successfully.');
    }

    public function edit(Course $course): View
    {
        $course->load(['programs', 'syllabi' => fn ($q) => $q->latest()]);

        return view('courses.edit', [
            'course' => $course,
            'programs' => Program::orderBy('code')->get(),
            'curriculumYears' => SyllabusController::curriculumYearOptions(),
        ]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $validated = $this->validateCourse($request, $course->id);
        $placements = $validated['placements'];
        unset($validated['placements']);
        $fileValidated = $this->validateFiles($request);

        $oldValues = $course->toArray();
        $oldProgramSnapshot = $this->programSnapshot($course);

        try {
            $course->update($validated);
            $course->programs()->sync($placements);
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

        $newProgramSnapshot = $this->programSnapshot($course->fresh());
        if ($oldProgramSnapshot !== $newProgramSnapshot) {
            $changed['old'] = array_merge($changed['old'] ?? [], ['programs' => $oldProgramSnapshot]);
            $changed['new'] = array_merge($changed['new'] ?? [], ['programs' => $newProgramSnapshot]);
        }

        $this->recordTrail($request, 'updated', $course, $changed['old'] ?? null, $changed['new'] ?? null);

        return redirect()->route('courses.index')->with('status', 'Course updated successfully.');
    }

    private function programSnapshot(Course $course): array
    {
        return $course->programs()
            ->get()
            ->mapWithKeys(fn ($program) => [
                $program->code => [
                    'year_level' => $program->pivot->year_level,
                    'semester' => $program->pivot->semester,
                ],
            ])
            ->all();
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
        $validated = $request->validate([
            'program_ids' => ['required', 'array', 'min:1'],
            'program_ids.*' => ['integer', 'distinct', Rule::exists('programs', 'id')],
            'course_code' => [
                'required', 'string', 'max:20',
                Rule::unique('courses', 'course_code')->whereNull('deleted_at')->ignore($ignoreId),
            ],
            'title' => [
                'required', 'string', 'max:255',
                Rule::unique('courses', 'title')->whereNull('deleted_at')->ignore($ignoreId),
            ],
            'year_level' => ['required', 'array', 'min:1'],
            'semester' => ['required', 'array', 'min:1'],
            'prerequisite' => ['nullable', 'string', 'max:255'],
            'corequisite' => ['nullable', 'string', 'max:255'],
            'lecture_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'lab_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'credited_units' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'tuition_hours' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
        ]);

        $placementRules = [];
        foreach ($validated['program_ids'] as $programId) {
            $placementRules["year_level.{$programId}"] = ['required', 'integer', 'min:1', 'max:4'];
            $placementRules["semester.{$programId}"] = ['required', 'in:1st,2nd,summer'];
        }
        $request->validate($placementRules);

        $validated['placements'] = collect($validated['program_ids'])
            ->mapWithKeys(fn ($programId) => [
                (int) $programId => [
                    'year_level' => (int) $validated['year_level'][(string) $programId],
                    'semester' => $validated['semester'][(string) $programId],
                ],
            ])
            ->all();

        unset($validated['program_ids'], $validated['year_level'], $validated['semester']);

        return $validated;
    }

    private function validateFiles(Request $request): array
    {
        return $request->validate(array_merge(
            SyllabusFileService::validationRules(),
            ['curriculum_year' => SyllabusFileService::curriculumYearRule()]
        ));
    }
}
