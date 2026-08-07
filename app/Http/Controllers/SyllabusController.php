<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\Syllabus;
use App\Services\SyllabusTextExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Syllabus upload/download/preview.
 *
 * Storage: files go on the 'local' disk (storage/app/private — not
 * web-accessible directly). download()/preview() are the only ways to
 * reach a file's bytes — one controlled point of access instead of
 * guessable public URLs. Both are public (matches the role table: even a
 * public visitor can view/download); it's upload that's gated behind
 * auth+role in routes/web.php.
 *
 * Upload accepts a PDF and/or a DOCX in the same submission (two separate
 * file inputs, each optional, at least one required) — each becomes its
 * own Syllabus row, since the schema already supports multiple rows per
 * subject and latestSyllabus() just picks the newest.
 *
 * Text extraction (raw_text, for SearchController's syllabus-content
 * search) runs synchronously right after each file is stored — see
 * SyllabusTextExtractor. No queue worker involved; status flips straight
 * to 'processed' or 'failed'.
 */
class SyllabusController extends Controller
{
    private const MAX_FILE_KILOBYTES = 20480; // 20MB

    /** field name => [validation mimes rule, stored file_type] */
    private const FILE_INPUTS = [
        'file_pdf' => 'pdf',
        'file_docx' => 'docx',
    ];

    public function __construct(private readonly SyllabusTextExtractor $extractor)
    {
    }

    /** Dropdown options for curriculum_year — current academic year ± a couple. */
    public static function curriculumYearOptions(): array
    {
        $startYear = (int) date('Y') - 2;

        return collect(range($startYear, $startYear + 4))
            ->map(fn (int $y) => "{$y}-" . ($y + 1))
            ->all();
    }

    public function create(Subject $subject): View
    {
        return view('syllabi.upload', [
            'subject' => $subject,
            'curriculumYears' => self::curriculumYearOptions(),
        ]);
    }

    public function store(Request $request, Subject $subject): RedirectResponse
    {
        $validated = $request->validate([
            'file_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:' . self::MAX_FILE_KILOBYTES],
            'file_docx' => ['nullable', 'file', 'mimes:docx', 'max:' . self::MAX_FILE_KILOBYTES],
            'curriculum_year' => ['nullable', 'string', 'in:' . implode(',', self::curriculumYearOptions())],
        ]);

        if (!$request->hasFile('file_pdf') && !$request->hasFile('file_docx')) {
            return back()
                ->withErrors(['file_pdf' => 'Kailangan ng kahit isang file — PDF o DOCX.'])
                ->withInput();
        }

        $notes = [];

        foreach (self::FILE_INPUTS as $field => $fileType) {
            if (!$request->hasFile($field)) {
                continue;
            }

            $file = $validated[$field];
            $storedPath = $file->store("syllabi/{$subject->id}", 'local');
            $absolutePath = Storage::disk('local')->path($storedPath);
            $rawText = $this->extractor->extract($absolutePath, $fileType);

            $syllabus = Syllabus::create([
                'subject_id' => $subject->id,
                'file_path' => $storedPath,
                'file_type' => $fileType,
                'raw_text' => $rawText,
                'curriculum_year' => $validated['curriculum_year'] ?? null,
                'status' => $rawText !== null ? 'processed' : 'failed',
                'uploaded_by' => $request->user()->id,
            ]);

            $notes[] = strtoupper($fileType) . " #{$syllabus->id}: " . ($rawText !== null ? 'processed' : 'failed');
        }

        return redirect()
            ->route('subjects.show', $subject)
            ->with('status', 'Na-upload: ' . implode(', ', $notes) . '.');
    }

    public function download(Syllabus $syllabus): StreamedResponse
    {
        abort_if(!Storage::disk('local')->exists($syllabus->file_path), 404);

        $subject = $syllabus->subject;
        $label = $subject ? $subject->subject_code : 'syllabus';
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
