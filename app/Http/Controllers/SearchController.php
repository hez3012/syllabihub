<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Syllabus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Ask SyllabiHub" — scoped fuzzy search over curriculum data.
 *
 * No LLM involved. Two layers:
 *   1. MySQL FULLTEXT (BOOLEAN MODE, prefix-wildcarded on the last token)
 *      against courses(course_code, title) and syllabi(raw_text).
 *   2. Application-level fuzzy fallback (similar_text/levenshtein-style
 *      scoring) over courses only, used when FULLTEXT finds nothing —
 *      covers typos that FULLTEXT can't (it matches literal tokens).
 *
 * Every result traces back to a real row in courses/syllabi — nothing is
 * generated, so there's no hallucination risk.
 */
class SearchController extends Controller
{
    /** Minimum similarity percentage (0-100) for a fuzzy match to count. */
    private const FUZZY_THRESHOLD = 62.0;

    /** Cap on how many results go back to the client. */
    private const MAX_RESULTS = 15;

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $query = trim($validated['q'] ?? '');
        $results = $this->performSearch($query);

        return response()->json([
            'query' => $query,
            'count' => count($results),
            'results' => $results,
        ]);
    }

    /**
     * The actual course+syllabus retrieval, split out from search() so
     * ChatbotService can reuse it as grounding context for "Ask
     * SyllabiHub" chat replies (per Rico, 2026-08-12) without going
     * through HTTP or duplicating the FULLTEXT/fuzzy logic. Same
     * no-LLM, traces-back-to-a-real-row search either way — the chatbot
     * only adds a conversational layer on top of this, it doesn't
     * change how results are found.
     *
     * @param  bool  $allowFuzzy  false skips the PHP fuzzy fallback, keeping
     *                            only exact/prefix FULLTEXT matches. Off by
     *                            default only for ChatbotService's per-
     *                            keyword retry (see its gatherResults()) —
     *                            fuzzy scoring a single generic word (e.g.
     *                            "credit", "ilan") against every course
     *                            can coincidentally clear the similarity
     *                            threshold and surface an unrelated
     *                            course, which is worse there than
     *                            finding nothing.
     * @return array<int, array<string, mixed>>
     */
    public function performSearch(string $query, bool $allowFuzzy = true): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $courseResults = $this->searchCourses($query, $allowFuzzy);
        $syllabusResults = $this->searchSyllabusContent($query);

        $results = $this->mergeResults($courseResults, $syllabusResults);

        return array_slice($results, 0, self::MAX_RESULTS);
    }

    /**
     * Course search: try FULLTEXT first (fast, index-backed). If it finds
     * nothing — most likely a typo — fall back to a PHP-side fuzzy scan.
     * The courses table is curriculum-sized (tens to low hundreds of rows),
     * so scanning it in PHP for the fallback is cheap.
     */
    private function searchCourses(string $query, bool $allowFuzzy = true): array
    {
        $boolean = $this->toBooleanQuery($query);

        if ($boolean !== '') {
            $matches = Course::query()
                ->select('courses.*')
                ->selectRaw('MATCH(course_code, title) AGAINST(? IN BOOLEAN MODE) as relevance', [$boolean])
                ->whereRaw('MATCH(course_code, title) AGAINST(? IN BOOLEAN MODE)', [$boolean])
                ->with(['program', 'latestSyllabus'])
                ->orderByDesc('relevance')
                ->limit(self::MAX_RESULTS)
                ->get();

            if ($matches->isNotEmpty()) {
                return $matches->map(fn (Course $c) => $this->formatCourse($c, 'fulltext', 100.0))->all();
            }
        }

        return $allowFuzzy ? $this->fuzzyCourseSearch($query) : [];
    }

    private function fuzzyCourseSearch(string $query): array
    {
        $needle = strtolower($query);
        $needleCompact = preg_replace('/\s+/', '', $needle);

        $courses = Course::with(['program', 'latestSyllabus'])->get();

        $scored = [];

        foreach ($courses as $course) {
            $combined = strtolower($course->course_code . ' ' . $course->title);
            $combinedCompact = preg_replace('/\s+/', '', $combined);

            // Direct substring match (handles "comp016" vs "COMP 016",
            // or a query that's just part of the title) — treat as strong.
            if (str_contains($combined, $needle) || str_contains($combinedCompact, $needleCompact)) {
                $scored[] = [$course, 95.0];
                continue;
            }

            similar_text($needle, $combined, $percentFull);
            similar_text($needleCompact, $combinedCompact, $percentCompact);

            $bestWordPercent = 0.0;
            foreach (preg_split('/\s+/', $combined) as $word) {
                if ($word === '') {
                    continue;
                }
                similar_text($needle, $word, $wordPercent);
                $bestWordPercent = max($bestWordPercent, $wordPercent);
            }

            $score = max($percentFull, $percentCompact, $bestWordPercent);

            if ($score >= self::FUZZY_THRESHOLD) {
                $scored[] = [$course, $score];
            }
        }

        usort($scored, fn (array $a, array $b) => $b[1] <=> $a[1]);
        $scored = array_slice($scored, 0, self::MAX_RESULTS);

        return array_map(
            fn (array $pair) => $this->formatCourse($pair[0], 'fuzzy', round($pair[1], 1)),
            $scored
        );
    }

    /**
     * Search inside uploaded syllabus text. FULLTEXT only — raw_text can be
     * long, so a PHP-side fuzzy scan here would be expensive and isn't
     * needed for the "Ask SyllabiHub" use case (course lookup is the
     * primary flow; content search is a bonus signal).
     */
    private function searchSyllabusContent(string $query): array
    {
        $boolean = $this->toBooleanQuery($query);

        if ($boolean === '') {
            return [];
        }

        $matches = Syllabus::query()
            ->select('syllabi.*')
            ->selectRaw('MATCH(raw_text) AGAINST(? IN BOOLEAN MODE) as relevance', [$boolean])
            ->whereRaw('MATCH(raw_text) AGAINST(? IN BOOLEAN MODE)', [$boolean])
            ->whereNotNull('raw_text')
            ->with('course.program')
            ->orderByDesc('relevance')
            ->limit(self::MAX_RESULTS)
            ->get();

        return $matches
            ->filter(fn (Syllabus $syllabus) => $syllabus->course !== null)
            ->map(function (Syllabus $syllabus) use ($query) {
                $course = $syllabus->course;

                return [
                    'course_id' => $course->id,
                    'course_code' => $course->course_code,
                    'title' => $course->title,
                    'program' => $course->program?->code,
                    'year_level' => $course->year_level,
                    'semester' => $course->semester,
                    'match_type' => 'syllabus_content',
                    'score' => 92.0,
                    'has_syllabus' => true,
                    'syllabus_id' => $syllabus->id,
                    'snippet' => $this->makeSnippet($syllabus->raw_text, $query),
                ];
            })
            ->all();
    }

    /**
     * Turn a free-text query into a MySQL BOOLEAN MODE expression:
     * every token is required (+), and the last token gets a trailing
     * wildcard so partial/in-progress typing (autocomplete) still matches
     * — e.g. "web dev" -> +web +dev*
     *
     * Mixed alnum tokens are split on letter/digit boundaries ("comp016"
     * -> "comp", "016") so a course code typed without its space still
     * lines up with the index, which tokenizes "COMP 016" as two words.
     * This resolves formatting differences precisely via FULLTEXT instead
     * of leaning on the fuzzy fallback, which can't tell "COMP 016" from
     * "COMP 001" as confidently once you're just comparing digit runs.
     */
    private function toBooleanQuery(string $query): string
    {
        $rawTokens = preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY);

        $tokens = [];
        foreach ($rawTokens as $raw) {
            $clean = preg_replace('/[+\-<>()~*"@]+/', '', $raw);
            if ($clean === '' || !preg_match_all('/[a-z]+|[0-9]+/i', $clean, $m)) {
                continue;
            }
            array_push($tokens, ...$m[0]);
        }

        if (empty($tokens)) {
            return '';
        }

        $last = count($tokens) - 1;

        return implode(' ', array_map(
            fn (int $i, string $t) => '+' . $t . ($i === $last ? '*' : ''),
            array_keys($tokens),
            $tokens
        ));
    }

    private function makeSnippet(?string $text, string $query, int $radius = 80): ?string
    {
        if (!$text) {
            return null;
        }

        $pos = stripos($text, $query);

        if ($pos === false) {
            $firstWord = strtok($query, " ");
            $pos = $firstWord ? stripos($text, $firstWord) : false;
        }

        if ($pos === false) {
            return trim(substr($text, 0, $radius * 2)) . '...';
        }

        $start = max(0, $pos - $radius);
        $snippet = trim(substr($text, $start, $radius * 2));

        return ($start > 0 ? '...' : '') . $snippet . '...';
    }

    private function formatCourse(Course $course, string $matchType, float $score): array
    {
        $latest = $course->relationLoaded('latestSyllabus')
            ? $course->latestSyllabus
            : $course->latestSyllabus()->first();

        return [
            'course_id' => $course->id,
            'course_code' => $course->course_code,
            'title' => $course->title,
            'program' => $course->program?->code,
            'year_level' => $course->year_level,
            'semester' => $course->semester,
            'prerequisite' => $course->prerequisite,
            'corequisite' => $course->corequisite,
            'lecture_hours' => $course->lecture_hours,
            'lab_hours' => $course->lab_hours,
            'credited_units' => $course->credited_units,
            'tuition_hours' => $course->tuition_hours,
            'match_type' => $matchType,
            'score' => $score,
            'has_syllabus' => $latest !== null,
            'syllabus_id' => $latest?->id,
            'snippet' => null,
        ];
    }

    /**
     * Combine course-level and syllabus-content matches, deduping by
     * course id. A course that matched both keeps the higher score and
     * picks up the content snippet.
     */
    private function mergeResults(array $courseResults, array $syllabusResults): array
    {
        $byCourse = [];

        foreach ($courseResults as $result) {
            $byCourse[$result['course_id']] = $result;
        }

        foreach ($syllabusResults as $result) {
            $id = $result['course_id'];

            if (!isset($byCourse[$id])) {
                $byCourse[$id] = $result;
                continue;
            }

            $existing = $byCourse[$id];
            $existing['snippet'] = $result['snippet'];
            $existing['has_syllabus'] = true;
            $existing['syllabus_id'] = $result['syllabus_id'];
            $existing['score'] = max($existing['score'], $result['score']);
            $byCourse[$id] = $existing;
        }

        $results = array_values($byCourse);
        usort($results, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $results;
    }
}
