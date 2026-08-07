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
 * Syllabus upload/download.
 *
 * Storage: files go on the 'local' disk (storage/app/private — not
 * web-accessible directly). Downloads always go through download() below
 * so there's one controlled point of access instead of guessable public
 * URLs. Download itself is public (matches the role table: even a public
 * visitor can view/download), it's upload that's gated behind auth+role
 * in routes/web.php.
 *
 * Text extraction (raw_text, for SearchController's syllabus-content
 * search) runs synchronously right after the file is stored — see
 * SyllabusTextExtractor. No queue worker involved; status flips straight
 * to 'processed' or 'failed'.
 */
class SyllabusController extends Controller
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'docx'];
    private const MAX_FILE_KILOBYTES = 20480; // 20MB

    public function __construct(private readonly SyllabusTextExtractor $extractor)
    {
    }

    public function create(Subject $subject): View
    {
        return view('syllabi.upload', compact('subject'));
    }

    public function store(Request $request, Subject $subject): RedirectResponse
    {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:' . implode(',', self::ALLOWED_EXTENSIONS),
                'max:' . self::MAX_FILE_KILOBYTES,
            ],
            'curriculum_year' => ['nullable', 'string', 'max:20'],
        ]);

        $file = $validated['file'];
        $extension = strtolower($file->getClientOriginalExtension());
        $fileType = $extension === 'pdf' ? 'pdf' : 'docx';

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

        $statusNote = $rawText !== null
            ? 'Status: processed — na-extract ang text para sa search.'
            : 'Status: failed — hindi na-extract ang text (baka corrupted o scanned/image-only ang file). Nakasave pa rin ang file, puwede pa ring i-download.';

        return redirect()
            ->route('subjects.show', $subject)
            ->with('status', "Na-upload ang syllabus (#{$syllabus->id}). {$statusNote}");
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
}
