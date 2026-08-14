<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Syllabus;
use Illuminate\Support\Facades\Storage;

/**
 * Shared PDF/DOCX upload handling — used by both SyllabusController (the
 * standalone upload page) and CourseController (inline upload/replace on
 * Add Course and Edit Course), so the validation rules, storage, text
 * extraction, and "replace, don't append" behavior only live in one place.
 *
 * Each of file_pdf/file_docx is independent and optional — a caller may
 * submit one, the other, or both in the same request (each becomes its own
 * Syllabus row). Per Rico, 2026-08-12: re-uploading a file type for a
 * course REPLACES that course's existing syllabus of the same type
 * (soft-deleted, not left to accumulate alongside the new one) — a
 * curriculum update supersedes the old file rather than adding a version
 * next to it.
 */
class SyllabusFileService
{
    // Per-file cap. Must stay <= php.ini's upload_max_filesize (200M as of
    // 2026-08-12) or valid uploads get silently truncated before Laravel
    // ever sees them.
    public const MAX_FILE_KILOBYTES = 204800; // 200MB

    /** field name => stored file_type */
    private const FILE_INPUTS = [
        'file_pdf' => 'pdf',
        'file_docx' => 'docx',
    ];

    public function __construct(private readonly SyllabusTextExtractor $extractor)
    {
    }

    public static function validationRules(): array
    {
        return [
            'file_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:' . self::MAX_FILE_KILOBYTES],
            'file_docx' => ['nullable', 'file', 'mimes:docx', 'max:' . self::MAX_FILE_KILOBYTES],
        ];
    }

    /**
     * curriculum_year rule: required_with, not an unconditional required,
     * on BOTH the standalone upload page and the inline Add/Edit Course
     * fields. That's deliberate even though the standalone page always
     * needs a file in the end — SyllabusController::store() enforces "at
     * least one file" itself, after validation, so if curriculum_year were
     * unconditionally required here, submitting a completely empty form
     * would bounce back complaining about the year instead of the missing
     * file. required_with keeps both error messages correct: no file at
     * all -> "need a file"; a file with no year -> "need a year". Per
     * Rico, 2026-08-12: a syllabus file may never be uploaded without a
     * curriculum year attached.
     */
    public static function curriculumYearRule(): array
    {
        return ['required_with:file_pdf,file_docx', 'string', 'in:' . implode(',', \App\Http\Controllers\SyllabusController::curriculumYearOptions())];
    }

    public static function hasAnyFile(array $validated): bool
    {
        return ($validated['file_pdf'] ?? null) !== null || ($validated['file_docx'] ?? null) !== null;
    }

    /**
     * @param  array  $validated  output of a request validated against self::validationRules()
     * @return string[] one human-readable note per file processed
     */
    public function storeFor(Course $course, array $validated, int $uploadedByUserId, ?string $curriculumYear): array
    {
        $notes = [];

        foreach (self::FILE_INPUTS as $field => $fileType) {
            $file = $validated[$field] ?? null;
            if (!$file) {
                continue;
            }

            $storedPath = $file->store("syllabi/{$course->id}", 'local');
            $absolutePath = Storage::disk('local')->path($storedPath);
            $rawText = $this->extractor->extract($absolutePath, $fileType);

            // Replace, don't append: retire this course's existing
            // syllabus of the same file type before adding the new one.
            Syllabus::where('course_id', $course->id)
                ->where('file_type', $fileType)
                ->delete();

            $syllabus = Syllabus::create([
                'course_id' => $course->id,
                'file_path' => $storedPath,
                'file_type' => $fileType,
                'original_filename' => $file->getClientOriginalName(),
                'raw_text' => $rawText,
                'curriculum_year' => $curriculumYear,
                'status' => $rawText !== null ? 'processed' : 'failed',
                'uploaded_by' => $uploadedByUserId,
            ]);

            // Display-only capitalization for the flash message — the
            // stored 'status' column itself stays lowercase (queried
            // elsewhere via Syllabus::statusLabel() for display).
            $notes[] = strtoupper($fileType) . " #{$syllabus->id}: " . ($rawText !== null ? 'Processed' : 'Failed');
        }

        return $notes;
    }
}
