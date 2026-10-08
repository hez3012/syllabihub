<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Program;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

/**
 * Bulk-imports the BSIT and DIT curricula from the spreadsheet CSVs.
 *
 *   php artisan curriculum:import --purge --dry-run
 *   php artisan curriculum:import --purge
 *
 * Built for the course_program design: ONE course row per course code, and
 * one course_program row per program it belongs to (with that program's
 * year level and semester). A subject shared by BSIT and DIT therefore
 * exists once, placed in both programs, each with its own year/semester.
 *
 * - Reads database/data/<program code lowercase>_curriculum.csv for every
 *   program in the database (bsit_curriculum.csv, dit_curriculum.csv).
 * - Both files are read in ONE run so shared courses can be merged.
 * - Idempotent: matches courses by code, updates in place, re-syncs the
 *   placements. Safe to re-run after the sheets are corrected.
 * - A course has a single prerequisite field, so when BSIT and DIT list
 *   different prerequisites for the same course they are combined
 *   ("COMP 009, INTE 202") and reported so someone can decide.
 * - --purge soft-deletes courses in these programs that appear in none of
 *   the files (the old sample data). Soft delete only.
 * - --dry-run reports without writing anything.
 */
class ImportCurriculum extends Command
{
    protected $signature = 'curriculum:import
        {--dir=database/data : Folder with the <program>_curriculum.csv files}
        {--purge : Soft-delete courses that are in none of the files (removes sample data)}
        {--dry-run : Validate and report only, write nothing}
        {--force : Skip the confirmation prompt for --purge}';

    protected $description = 'Import the BSIT/DIT curricula (courses + program placements) from spreadsheet CSVs';

    private const YEARS = ['first' => 1, '1st' => 1, '1' => 1, 'second' => 2, '2nd' => 2, '2' => 2,
        'third' => 3, '3rd' => 3, '3' => 3, 'fourth' => 4, '4th' => 4, '4' => 4];

    public function handle(): int
    {
        $dir = (string) $this->option('dir');
        $dir = (str_contains($dir, ':') || str_starts_with($dir, '/') || str_starts_with($dir, '\\')) ? $dir : base_path($dir);
        $dry = (bool) $this->option('dry-run');

        $sources = [];
        foreach (Program::orderBy('id')->get() as $program) {
            $path = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . strtolower($program->code) . '_curriculum.csv';

            if (! is_file($path)) {
                $this->warn("No file for {$program->code} (looked for {$path}) — skipped.");

                continue;
            }

            [$rows, $problems] = $this->readCsv($path);
            foreach ($problems as $problem) {
                $this->warn("{$program->code}: {$problem}");
            }

            $this->line("{$program->code}: " . count($rows) . ' course rows read.');
            $sources[$program->code] = ['id' => $program->id, 'rows' => $rows];
        }

        if (! $sources) {
            $this->error('No curriculum CSV files found. Expected e.g. database/data/bsit_curriculum.csv');

            return self::FAILURE;
        }

        [$catalog, $notes] = $this->buildCatalog($sources);

        foreach ($notes as $note) {
            $this->warn($note);
        }

        $placements = array_sum(array_map(fn ($c) => count($c['placements']), $catalog));
        $this->info(($dry ? '[DRY RUN] ' : '') . count($catalog) . " distinct courses, {$placements} program placements.");

        if ($this->option('purge')) {
            $this->purgeStale($sources, array_keys($catalog), $dry);
        }

        return $dry ? $this->dryRun($catalog) : $this->import($catalog);
    }

    // ------------------------------------------------------------------
    // Merge both programs' rows into one entry per course code
    // ------------------------------------------------------------------

    /**
     * @param  array<string, array{id:int, rows:array}>  $sources
     * @return array{0: array<string, array<string,mixed>>, 1: string[]}
     */
    private function buildCatalog(array $sources): array
    {
        $catalog = [];
        $preByProgram = [];
        $notes = [];

        foreach ($sources as $programCode => $source) {
            foreach ($source['rows'] as $row) {
                $code = $row['course_code'];

                if (! isset($catalog[$code])) {
                    $catalog[$code] = [
                        'attrs' => [
                            'title' => $row['title'],
                            'lecture_hours' => $row['lecture_hours'],
                            'lab_hours' => $row['lab_hours'],
                            'credited_units' => $row['credited_units'],
                            'tuition_hours' => $row['tuition_hours'],
                        ],
                        'pre' => [],
                        'co' => [],
                        'placements' => [],
                    ];
                } else {
                    foreach (['title', 'lecture_hours', 'lab_hours', 'credited_units', 'tuition_hours'] as $field) {
                        if ($catalog[$code]['attrs'][$field] != $row[$field]) {
                            $notes[] = "{$code}: {$field} differs between programs ('{$catalog[$code]['attrs'][$field]}' vs '{$row[$field]}' in {$programCode}) — kept the first.";
                        }
                    }
                }

                foreach ($this->splitCodes($row['prerequisite']) as $p) {
                    if (! in_array($p, $catalog[$code]['pre'], true)) {
                        $catalog[$code]['pre'][] = $p;
                    }
                }
                foreach ($this->splitCodes($row['corequisite']) as $p) {
                    if (! in_array($p, $catalog[$code]['co'], true)) {
                        $catalog[$code]['co'][] = $p;
                    }
                }

                $preByProgram[$code][$programCode] = (string) $row['prerequisite'];
                $catalog[$code]['placements'][$source['id']] = [
                    'year_level' => $row['year_level'],
                    'semester' => $row['semester'],
                ];
            }
        }

        // Report shared courses whose prerequisites differ per program.
        foreach ($preByProgram as $code => $byProgram) {
            if (count($byProgram) > 1 && count(array_unique($byProgram)) > 1) {
                $parts = [];
                foreach ($byProgram as $programCode => $pre) {
                    $parts[] = "{$programCode}: " . ($pre === '' ? '(none)' : $pre);
                }
                $stored = $catalog[$code]['pre'] ? implode(', ', $catalog[$code]['pre']) : '(none)';
                $notes[] = "{$code}: prerequisite differs — " . implode(' | ', $parts) . " → stored '{$stored}'. Confirm with the team.";
            }
        }

        // Prerequisite sanity check (warn only).
        $known = array_keys($catalog);
        foreach ($catalog as $code => $c) {
            foreach (array_merge($c['pre'], $c['co']) as $p) {
                if (! in_array($p, $known, true)) {
                    $notes[] = "{$code}: prerequisite '{$p}' is not a course in the files — check the sheet for a typo.";
                }
            }
        }

        return [$catalog, $notes];
    }

    // ------------------------------------------------------------------

    private function import(array $catalog): int
    {
        $created = $updated = $unchanged = 0;
        $saved = [];
        $pending = $catalog;
        $errors = [];

        // Retry loop: a course can fail on the campus-wide unique title only
        // because an old sample course still holds it until a LATER row
        // renames it. Keep passing over the failures until a pass makes no
        // progress.
        do {
            $progress = false;
            $next = [];
            $errors = [];

            foreach ($pending as $code => $entry) {
                $attrs = $entry['attrs'] + [
                    'prerequisite' => $entry['pre'] ? implode(', ', $entry['pre']) : null,
                    'corequisite' => $entry['co'] ? implode(', ', $entry['co']) : null,
                ];

                try {
                    $existing = Course::where('course_code', $code)->first();

                    if ($existing) {
                        $existing->fill($attrs);
                        if ($existing->isDirty()) {
                            $existing->save();
                            $updated++;
                        } else {
                            $unchanged++;
                        }
                        $saved[$code] = $existing;
                    } else {
                        $saved[$code] = Course::create($attrs + ['course_code' => $code]);
                        $created++;
                    }

                    $progress = true;
                } catch (QueryException $e) {
                    $next[$code] = $entry;
                    $errors[$code] = $this->explainQueryError($e);
                }
            }

            $pending = $next;
        } while ($pending && $progress);

        // Program placements: exactly the programs each course is listed in.
        foreach ($saved as $code => $course) {
            $course->programs()->sync($catalog[$code]['placements']);
        }

        $this->newLine();
        $this->info("{$created} created, {$updated} updated, {$unchanged} unchanged, " . count($pending) . ' failed.');

        foreach ($pending as $code => $entry) {
            $this->error("  {$code} — {$errors[$code]}");
        }

        return $pending ? self::FAILURE : self::SUCCESS;
    }

    private function dryRun(array $catalog): int
    {
        $create = $update = 0;

        foreach (array_keys($catalog) as $code) {
            Course::where('course_code', $code)->exists() ? $update++ : $create++;
        }

        $this->info("[DRY RUN] would create {$create} courses, update {$update}, and set all program placements. Nothing was written.");

        return self::SUCCESS;
    }

    private function purgeStale(array $sources, array $keepCodes, bool $dry): void
    {
        $programIds = array_column($sources, 'id');

        $stale = Course::whereHas('programs', fn ($q) => $q->whereIn('programs.id', $programIds))
            ->whereNotIn('course_code', $keepCodes)
            ->withCount('syllabi')
            ->orderBy('course_code')
            ->get();

        if ($stale->isEmpty()) {
            $this->line('Purge: nothing to remove.');

            return;
        }

        $this->warn("Purge: {$stale->count()} course(s) are in none of the files and will be soft-deleted:");
        $this->table(
            ['Code', 'Title', 'Syllabi attached'],
            $stale->map(fn (Course $c) => [$c->course_code, $c->title, $c->syllabi_count])->all()
        );

        if ($dry) {
            return;
        }

        if (! $this->option('force') && ! $this->confirm('Soft-delete these courses?', false)) {
            $this->line('Purge skipped.');

            return;
        }

        $stale->each->delete();
        $this->info("Purged {$stale->count()} course(s).");
    }

    // ------------------------------------------------------------------
    // CSV parsing
    // ------------------------------------------------------------------

    /** @return array{0: array<int, array<string, mixed>>, 1: string[]} */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $problems = [];
        $map = null;
        $lineNo = 0;

        while ($map === null && ($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $lineNo++;
            $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($cells[0] ?? ''));
            $normalized = array_map(fn ($c) => strtolower(trim(preg_replace('/\s+/', ' ', (string) $c))), $cells);

            // The header row is the first row that has a code column.
            if (array_intersect($normalized, ['subject code', 'course code'])) {
                $map = array_flip(array_filter($normalized, fn ($v) => $v !== ''));
            }

            if ($lineNo > 25) {
                break;
            }
        }

        if ($map === null) {
            fclose($handle);

            return [[], ['Could not find a header row containing "Subject Code".']];
        }

        $col = fn (array $row, array $names) => $this->cell($row, $map, $names);
        $rows = [];
        $seen = [];

        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $lineNo++;
            $code = $this->clean($col($cells, ['subject code', 'course code']));

            if ($code === '') {
                continue; // blank / spacer row
            }

            $title = $this->clean($col($cells, ['description', 'title', 'course title']));
            $year = self::YEARS[strtolower($this->firstWord($col($cells, ['year level'])))] ?? null;
            $semester = $this->semester($col($cells, ['semester']));

            if ($title === '' || $year === null || $semester === null) {
                $problems[] = "Line {$lineNo} ({$code}): skipped — missing/unknown title, year level or semester.";

                continue;
            }

            if (isset($seen[$code])) {
                $problems[] = "Line {$lineNo} ({$code}): skipped — duplicate code in the file (first one kept).";

                continue;
            }

            if (mb_strlen($code) > 20 || mb_strlen($title) > 255) {
                $problems[] = "Line {$lineNo} ({$code}): skipped — code/title too long for the database.";

                continue;
            }

            $seen[$code] = true;
            $rows[] = [
                'course_code' => $code,
                'title' => $title,
                'year_level' => $year,
                'semester' => $semester,
                'prerequisite' => $this->clean($col($cells, ['prerequisite'])),
                'corequisite' => $this->clean($col($cells, ['co-requisite', 'corequisite'])),
                'lecture_hours' => $this->number($col($cells, ['lecture hours'])),
                'lab_hours' => $this->number($col($cells, ['laboratory hours', 'lab hours'])),
                'credited_units' => $this->number($col($cells, ['credited units', 'units'])),
                'tuition_hours' => $this->number($col($cells, ['tuition hours'])),
            ];
        }

        fclose($handle);

        return [$rows, $problems];
    }

    private function cell(array $row, array $map, array $names): string
    {
        foreach ($names as $name) {
            if (isset($map[$name])) {
                return (string) ($row[$map[$name]] ?? '');
            }
        }

        return '';
    }

    private function clean(string $value): string
    {
        // Collapse whitespace; a stray backtick in the sheet is meant to be an apostrophe.
        return trim(str_replace('`', "'", preg_replace('/\s+/u', ' ', $value)));
    }

    private function number(string $value): ?float
    {
        $value = trim($value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function firstWord(string $value): string
    {
        return strtok(trim($value), ' ') ?: '';
    }

    private function semester(string $value): ?string
    {
        $v = strtolower($value);

        return match (true) {
            str_contains($v, 'summer') => 'summer',
            str_contains($v, 'first'), str_contains($v, '1st') => '1st',
            str_contains($v, 'second'), str_contains($v, '2nd') => '2nd',
            default => null,
        };
    }

    /** @return string[] */
    private function splitCodes(?string $value): array
    {
        return $value ? array_values(array_filter(array_map('trim', preg_split('/[,;]/', $value)))) : [];
    }

    private function explainQueryError(QueryException $e): string
    {
        if (str_contains($e->getMessage(), 'Duplicate entry')) {
            return 'its code or title is already used by another live course (campus-wide unique index).';
        }

        return mb_substr($e->getMessage(), 0, 160);
    }
}
