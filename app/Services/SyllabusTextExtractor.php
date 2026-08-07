<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * Pulls plain text out of an uploaded syllabus file (PDF or DOCX) so it can
 * be stored in syllabi.raw_text and picked up by SearchController's
 * FULLTEXT search over syllabus content.
 *
 * Runs synchronously (called directly from SyllabusController::store()) —
 * no queue worker required. Fine for this project's scale (small files,
 * low upload volume); revisit if that stops being true.
 */
class SyllabusTextExtractor
{
    /**
     * @return string|null Extracted plain text, or null if nothing could
     *                      be extracted (caller decides how to record that).
     */
    public function extract(string $absolutePath, string $fileType): ?string
    {
        try {
            $text = match ($fileType) {
                'pdf' => $this->extractPdf($absolutePath),
                'docx' => $this->extractDocx($absolutePath),
                default => null,
            };

            $text = $text !== null ? trim($text) : null;

            return $text !== '' ? $text : null;
        } catch (Throwable $e) {
            Log::warning('Syllabus text extraction failed', [
                'path' => $absolutePath,
                'file_type' => $fileType,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function extractPdf(string $absolutePath): ?string
    {
        $parser = new PdfParser();

        return $parser->parseFile($absolutePath)->getText();
    }

    private function extractDocx(string $absolutePath): ?string
    {
        $phpWord = WordIOFactory::load($absolutePath, 'Word2007');
        $text = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $elementText = $element->getText();
                    // getText() can return a string or a nested array of
                    // runs depending on element type (TextRun vs Text).
                    $text[] = is_array($elementText) ? implode(' ', array_filter($elementText, 'is_string')) : $elementText;
                }
            }
        }

        return implode("\n", array_filter($text, fn ($t) => $t !== null && $t !== ''));
    }
}
