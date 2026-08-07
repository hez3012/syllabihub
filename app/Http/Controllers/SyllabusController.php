<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\Syllabus;
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
 * NOT implemented here (out of scope for "upload/download logic"):
 * text extraction into syllabi.raw_text. That needs a PDF/DOCX parser
 * library (e.g. smalot/pdfparser, phpoffice/phpword) which hasn't been
 * discussed/approved yet. Every upload is created with status='pending'
 * and raw_text=null — the syllabus-content half of SearchController's
 * fuzzy search has nothing to match against until that's built. Flagging
 * this explicitly so it isn't mistaken for "search is broken."
 */
class SyllabusController extends Controller
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'docx'];
    private const MAX_FILE_KILOBYTES = 20480; // 20MB

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

        $syllabus = Syllabus::create([
            'subject_id' => $subject->id,
            'file_path' => $storedPath,
            'file_type' => $fileType,
            'curriculum_year' => $validated['curriculum_year'] ?? null,
            'status' => 'pending',
            'uploaded_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('subjects.show', $subject)
            ->with('status', "Na-upload ang syllabus (#{$syllabus->id}). Status: pending.");
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
