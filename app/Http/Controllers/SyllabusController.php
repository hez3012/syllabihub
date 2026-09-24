<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Course;
use App\Models\Syllabus;
use App\Services\SyllabusFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SyllabusController extends Controller
{
    public function __construct(private readonly SyllabusFileService $files)
    {
    }

    public static function curriculumYearOptions(): array
    {
        $startYear = 2022;
        $endYear = max($startYear, (int) date('Y'));

        return collect(range($startYear, $endYear))
            ->map(fn (int $y) => "{$y}-" . ($y + 1))
            ->all();
    }

    public function create(Course $course): View
    {
        return view('syllabi.upload', [
            'course' => $course,
            'curriculumYears' => self::curriculumYearOptions(),
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $validated = $request->validate(array_merge(
            SyllabusFileService::validationRules(),
            ['curriculum_year' => SyllabusFileService::curriculumYearRule()]
        ));

        if (!SyllabusFileService::hasAnyFile($validated)) {
            return back()
                ->withErrors(['file_pdf' => 'At least one file is required — PDF or DOCX.'])
                ->withInput();
        }

        $notes = $this->files->storeFor(
            $course,
            $validated,
            $request->user()->id,
            $validated['curriculum_year'] ?? null
        );

        $user = $request->user();
        AuditTrail::create([
            'full_name' => $user->name,
            'email' => $user->email,
            'action' => 'updated',
            'subject_type' => Course::class,
            'subject_id' => $course->id,
            'description' => "Uploaded syllabus for: {$course->course_code} — {$course->title}",
            'old_values' => null,
            'new_values' => ['syllabus_files' => $notes],
            'created_at' => now(),
        ]);

        return redirect()
            ->route('courses.index')
            ->with('status', 'Uploaded: ' . implode(', ', $notes) . '.');
    }

    public function download(Syllabus $syllabus): StreamedResponse
    {
        abort_if(!Storage::disk('local')->exists($syllabus->file_path), 404);

        $course = $syllabus->course;
        $label = $course ? $course->course_code : 'syllabus';
        $year = $syllabus->curriculum_year ? "-{$syllabus->curriculum_year}" : '';
        $downloadName = str_replace(' ', '_', $label) . $year . '.' . $syllabus->file_type;

        return Storage::disk('local')->download($syllabus->file_path, $downloadName);
    }

    public function preview(Syllabus $syllabus): StreamedResponse
    {
        abort_if(!Storage::disk('local')->exists($syllabus->file_path), 404);
        abort_unless($syllabus->file_type === 'pdf', 415, 'Preview is only available for PDF syllabi.');

        return Storage::disk('local')->response($syllabus->file_path, null, [
            'Content-Disposition' => 'inline',
        ]);
    }
}
