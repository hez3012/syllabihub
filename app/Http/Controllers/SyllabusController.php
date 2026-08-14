<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Syllabus;
use App\Services\SyllabusFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Standalone syllabus upload page, plus download/preview.
 *
 * Storage: files go on the 'local' disk (storage/app/private — not
 * web-accessible directly). download()/preview() are the only ways to
 * reach a file's bytes — one controlled point of access instead of
 * guessable public URLs. All three require auth (CLAUDE.md §7).
 *
 * This is the "fast path" for faculty to jump straight to uploading —
 * CourseController::store()/update() also accept these same file_pdf/
 * file_docx fields inline on the Add/Edit Course forms, both delegating
 * to SyllabusFileService so the actual upload/replace logic lives in one
 * place. See that service for the validation rules and "replace, don't
 * append" behavior.
 *
 * Text extraction (raw_text, for SearchController's syllabus-content
 * search) runs synchronously right after each file is stored — see
 * SyllabusTextExtractor. No queue worker involved; status flips straight
 * to 'processed' or 'failed'.
 */
class SyllabusController extends Controller
{
    public function __construct(private readonly SyllabusFileService $files)
    {
    }

    /** Dropdown options for curriculum_year — fixed floor per Rico (2026-08-12), floating ceiling at the current year. */
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

        return redirect()
            ->route('courses.show', $course)
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

    /** Same file as download(), but inline disposition so a PDF renders in an <iframe> instead of forcing a download. */
    public function preview(Syllabus $syllabus): StreamedResponse
    {
        abort_if(!Storage::disk('local')->exists($syllabus->file_path), 404);
        abort_unless($syllabus->file_type === 'pdf', 415, 'Preview is only available for PDF syllabi.');

        return Storage::disk('local')->response($syllabus->file_path, null, [
            'Content-Disposition' => 'inline',
        ]);
    }
}
