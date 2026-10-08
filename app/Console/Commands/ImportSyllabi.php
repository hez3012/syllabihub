<?php

namespace App\Console\Commands;

use App\Http\Controllers\SyllabusController;
use App\Models\Course;
use App\Models\Syllabus;
use App\Models\User;
use App\Services\SyllabusTextExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bulk-attaches syllabus PDFs to the imported courses.
 *
 *   php artisan syllabus:import "C:\Syllabi" --dry-run
 *   php artisan syllabus:import "C:\Syllabi"
 *
 * - Put the syllabus files in one folder (subfolders are scanned too).
 * - database/data/syllabus_map.csv says which file belongs to which course
 *   code (copied from the "Syllabus link" column of the BSIT and DIT
 *   sheets; one row per course, because a shared course has ONE syllabus).
 * - PDF only. The .docx twins in the folder are ignored.
 * - Files are matched by name; case, spaces, punctuation, accents and the
 *   .docx/.pdf extension chain are ignored. If the name in the sheet isn't
 *   in the folder, the alternate name (the other program's sheet) is tried,
 *   then the ONE PDF whose name starts with the course code.
 * - Same steps as the upload form: stored under storage/app/private/
 *   syllabi/{course_id}/, text extracted (what Sage and search read), the
 *   course's older PDF syllabus is replaced (soft-deleted), a row is added
 *   to `syllabi` with the curriculum year and uploader.
 * - Safe to re-run: a course that already has this exact file is skipped
 *   (use --replace to force).
 * - --dry-run lists what would be attached and what's missing; writes nothing.
 */
class ImportSyllabi extends Command
{
    protected $signature = 'syllabus:import
        {folder : Folder that contains the syllabus files}
        {--map= : Mapping CSV (default database/data/syllabus_map.csv)}
        {--year=2025-2026 : Curriculum year recorded on the syllabi}
        {--user= : User id or email recorded as the uploader (default: first admin)}
        {--replace : Re-upload even if the course already has this exact file}
        {--dry-run : Report only, write nothing}';

    protected $description = 'Bulk-attach syllabus PDFs to imported courses';

    public function handle(SyllabusTextExtractor $extractor): int
    {
        $folder = rtrim((string) $this->argument('folder'), "\\/");
        if (! is_dir($folder)) {
            $this->error("Folder not found: {$folder}");

            return self::FAILURE;
        }

        $year = (string) $this->option('year');
        $validYears = SyllabusController::curriculumYearOptions();
        if (! in_array($year, $validYears, true)) {
            $this->error("Curriculum year '{$year}' isn't accepted by the app. Use one of: " . implode(', ', $validYears));

            return self::FAILURE;
        }

        $mapPath = $this->option('map') ?: base_path('database/data/syllabus_map.csv');
        if (! is_file($mapPath)) {
            $this->error("Mapping file not found: {$mapPath}");

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $uploaderId = $this->resolveUploader();
        if (! $dry && $uploaderId === null) {
            $this->error('No uploader found. Pass --user=<id or email>.');

            return self::FAILURE;
        }

        $map = $this->readMap($mapPath);
        $pdfs = $this->scanPdfs($folder);

        $this->info(($dry ? '[DRY RUN] ' : '') . count($map) . ' courses in the map, ' . count($pdfs) . " PDF files in {$folder}");

        $done = $skipped = 0;
        $problems = [];
        $notes = [];

        foreach ($map as $code => $names) {
            $course = Course::where('course_code', $code)->first();

            if (! $course) {
                $problems[] = "{$code}: course not found (did the curriculum import run?)";

                continue;
            }

            [$path, $note] = $this->findFile($pdfs, $code, $names);

            if ($path === null) {
                $problems[] = "{$code}: {$note}";

                continue;
            }

            $original = basename($path);

            if (! $this->option('replace')
                && Syllabus::where('course_id', $course->id)->where('file_type', 'pdf')->where('original_filename', $original)->exists()) {
                $skipped++;

                continue;
            }

            if ($note !== '') {
                $notes[] = "{$code}: {$note} -> {$original}";
            }

            if ($dry) {
                $this->line("  would attach  {$code}  <-  {$original}");
                $done++;

                continue;
            }

            try {
                $stored = "syllabi/{$course->id}/" . Str::random(40) . '.pdf';
                Storage::disk('local')->put($stored, file_get_contents($path));
                $rawText = $extractor->extract(Storage::disk('local')->path($stored), 'pdf');

                // Same "replace, don't append" rule as the upload form.
                Syllabus::where('course_id', $course->id)->where('file_type', 'pdf')->delete();

                Syllabus::create([
                    'course_id' => $course->id,
                    'file_path' => $stored,
                    'file_type' => 'pdf',
                    'original_filename' => $original,
                    'raw_text' => $rawText,
                    'curriculum_year' => $year,
                    'status' => $rawText !== null ? 'processed' : 'failed',
                    'uploaded_by' => $uploaderId,
                ]);

                $done++;
                $this->line("  attached  {$code}  <-  {$original}" . ($rawText === null ? '   (text extraction failed: attached, but not searchable)' : ''));
            } catch (Throwable $e) {
                $problems[] = "{$code}: " . mb_substr($e->getMessage(), 0, 160);
            }
        }

        $this->newLine();
        $this->info(($dry ? 'Would attach' : 'Attached') . " {$done}, skipped {$skipped} (already attached), " . count($problems) . ' problem(s).');

        foreach ($notes as $note) {
            $this->line("  note: {$note}");
        }

        foreach ($problems as $problem) {
            $this->warn("  {$problem}");
        }

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------

    /** @return array<string, string[]> course code => [primary name, alternate name?] */
    private function readMap(string $path): array
    {
        $handle = fopen($path, 'r');
        fgetcsv($handle, 0, ',', '"', ''); // header
        $map = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $code = trim(preg_replace('/\s+/', ' ', (string) ($row[0] ?? '')));
            $names = array_values(array_filter([trim((string) ($row[1] ?? '')), trim((string) ($row[2] ?? ''))], fn ($n) => $n !== ''));

            if ($code !== '' && $names) {
                $map[$code] = $names;
            }
        }

        fclose($handle);

        return $map;
    }

    /** @return array<string, string> absolute path => normalized name (PDF files only) */
    private function scanPdfs(string $folder): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {
                $files[$file->getPathname()] = $this->normalize($file->getFilename());
            }
        }

        ksort($files);

        return $files;
    }

    /** @return array{0: ?string, 1: string} [path, note] */
    private function findFile(array $pdfs, string $code, array $names): array
    {
        foreach ($names as $i => $wanted) {
            $target = $this->normalize($wanted);
            $hits = array_keys(array_filter($pdfs, fn ($n) => $n === $target));

            if ($hits) {
                // Several identical-looking copies: prefer the one named exactly like the sheet.
                $exact = array_values(array_filter($hits, fn ($p) => basename($p) === $wanted));
                $pick = $exact[0] ?? $hits[0];
                $note = $i > 0 ? "matched the other program's file name" : '';

                if (count($hits) > 1 && ! $exact) {
                    $note = trim($note . ' ' . count($hits) . ' near-identical copies in the folder, used the first');
                }

                return [$pick, $note];
            }
        }

        // Fallback: the one PDF starting with the course code.
        $prefix = $this->normalize($code);
        $starts = array_keys(array_filter($pdfs, fn ($n) => str_starts_with($n, $prefix)));

        if (count($starts) === 1) {
            return [$starts[0], 'name in the sheet not found, used the only PDF starting with this code'];
        }

        if (count($starts) > 1) {
            return [null, 'several PDFs start with this code and none matches the sheet: ' . implode(' | ', array_map('basename', $starts))];
        }

        return [null, 'no PDF found in the folder (sheet says: ' . $names[0] . ')'];
    }

    private function normalize(string $name): string
    {
        // Drop the extension chain: ".pdf", ".docx", ".docx.pdf"
        $name = preg_replace('/(\.docx?)?\.(pdf|docx?)$/i', '', $name);

        if (class_exists(\Normalizer::class)) {
            $name = \Normalizer::normalize($name, \Normalizer::FORM_D) ?: $name;
        }

        // Accents: drop combining marks (decomposed form) and map the common
        // precomposed letters (works even when PHP's intl extension is off).
        $name = preg_replace('/\p{M}+/u', '', $name);
        $name = strtr($name, [
            'ñ' => 'n', 'Ñ' => 'N', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
        ]);

        return preg_replace('/[^a-z0-9]/', '', strtolower($name));
    }

    private function resolveUploader(): ?int
    {
        $opt = $this->option('user');

        if ($opt !== null && $opt !== '') {
            $user = is_numeric($opt) ? User::find((int) $opt) : User::where('email', $opt)->first();

            return $user?->id;
        }

        return User::where('role', 'admin')->orderBy('id')->value('id');
    }
}
