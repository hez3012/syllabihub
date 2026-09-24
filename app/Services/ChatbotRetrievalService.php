<?php

namespace App\Services;

use App\Http\Controllers\SearchController;
use App\Models\Course;
use App\Models\Program;
use App\Models\Syllabus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * RAG retrieval for "Sage" — given a message already classified
 * by ChatbotQueryClassifier, runs the DB query that actually answers that
 * TYPE of question and hands back structured context for ChatbotService
 * to hand to Groq. Per Rico, 2026-08-12 (chatbot-test-cases.md):
 * different question shapes need genuinely different queries, not just a
 * text search — "Ano ang prereq ng COMP 003?" needs a join/chain lookup,
 * "Ilan ang total units ng BSIT?" needs an aggregate, etc.
 *
 * Every retrieve*() method returns:
 *   ['courses' => array<course-shaped row>, 'notes' => string[]]
 * `courses` rows share the exact field set SearchController::
 * formatCourse() already returns (course_id, course_code, title,
 * program, year_level, semester, prerequisite, corequisite,
 * lecture_hours, lab_hours, credited_units, tuition_hours, match_type) —
 * one consistent shape regardless of which strategy produced it, so
 * ChatbotService doesn't need to branch on query type downstream.
 * `notes` carries anything that isn't really "a course" — an aggregate
 * total, a comparison summary, an uploader/date fact — as plain English
 * sentences appended to the prompt context alongside the course list.
 *
 * Text-search categories (course_lookup, general_search,
 * syllabus_content) reuse SearchController::performSearch() rather than
 * duplicating its FULLTEXT/fuzzy logic — see textSearch()'s docblock.
 * Everything else here is new structured querying that has no equivalent
 * in SearchController at all.
 *
 * "Course" terminology (2026-08-13, per Rico/supervisor — was "Subject"
 * before): every regex below that matches literal words a real user
 * might type still recognizes "subject"/"subjects" as a synonym
 * alongside "course"/"courses" — see the classifier's own docblock for
 * why. Generated note text sent to Groq as context, and all internal
 * identifiers/variables/comments, consistently use the new "course"
 * terminology regardless of which word the user actually typed.
 */
class ChatbotRetrievalService
{
    /**
     * Filler words stripped before falling back to per-keyword search —
     * see textSearch()'s docblock. Not exhaustive, just the common
     * English/Tagalog function words most likely to show up in a
     * faculty member's question.
     */
    private const STOPWORDS = [
        'the', 'is', 'a', 'an', 'to', 'of', 'and', 'in', 'on', 'for', 'where', 'can', 'you', 'we',
        'do', 'does', 'find', 'show', 'me', 'please', 'my', 'this', 'that', 'are', 'could', 'would',
        'have', 'has', 'with', 'what', 'who', 'how', 'get',
        'saan', 'makikita', 'yung', 'ang', 'ng', 'sa', 'ba', 'po', 'opo', 'meron', 'may', 'mayroon',
        'kayo', 'namin', 'natin', 'ko', 'mo', 'niya', 'kanila', 'atin', 'akin', 'para', 'kung',
        'paano', 'ano', 'sino', 'kailan', 'bakit', 'pwede', 'puwede', 'gusto', 'hanapin', 'hanap',
        'ito', 'iyan', 'iyon', 'dito', 'diyan', 'doon', 'mga', 'at', 'o', 'kasi', 'kaya', 'lang',
        'na', 'pa', 'din', 'rin', 'hindi', 'oo', 'naman', 'kami', 'tayo', 'sila',
    ];

    public function __construct(private readonly SearchController $search)
    {
    }

    /**
     * Accounts a system administrator would consider privileged — the
     * ONLY roles allowed to see faculty-account counts, course change
     * request details, or system-wide upload activity via Sage. Mirrors
     * the exact same boundary the rest of the app already draws
     * (CLAUDE.md §7/§8: admin/intern manage faculty accounts and review
     * change requests; the admin/intern dashboard shows system-wide
     * recent uploads, faculty's dashboard only shows their own courses)
     * — Sage isn't inventing a new rule, just not accidentally handing
     * out through chat what the UI itself already keeps admin/intern-only.
     */
    private const PRIVILEGED_ROLES = ['admin'];

    /**
     * @param  string  $type  a ChatbotQueryClassifier::* constant
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  string  $role  the ASKING user's account role (admin/faculty/intern)
     *                        — deliberately defaults to the least-privileged
     *                        value so a caller that forgets to pass it fails
     *                        closed (denies access) rather than open.
     * @return array{courses: array<int, array<string, mixed>>, notes: string[]}
     */
    public function retrieve(string $type, string $message, array $history = [], string $role = 'faculty'): array
    {
        return match ($type) {
            ChatbotQueryClassifier::PREREQUISITE => $this->retrievePrerequisite($message, $history),
            ChatbotQueryClassifier::FACULTY_UPLOADER => $this->retrieveFacultyUploader($message, $history, $role),
            ChatbotQueryClassifier::SYLLABUS_AVAILABILITY => $this->retrieveSyllabusAvailability($message, $history),
            ChatbotQueryClassifier::YEAR_SEMESTER => $this->retrieveYearSemester($message, $history),
            ChatbotQueryClassifier::STATS => $this->retrieveStats($message, $history, $role),
            ChatbotQueryClassifier::PROGRAM_COMPARISON => $this->retrieveProgramComparison(),
            ChatbotQueryClassifier::COURSE_CATEGORY => $this->retrieveCourseCategory($message),
            // No DB query needed at all — these are answered purely from
            // the system prompt's own instructions/knowledge, or (for
            // GIBBERISH/EMOTIONAL/ADVERSARIAL/IMPOSSIBLE_ACTION) don't
            // need database grounding in the first place; an empty
            // Context is expected and fine for every one of these (see
            // SYSTEM_PROMPT's "greeting"/"out_of_scope"/"system_help"
            // exemption list, extended 2026-08-13 to cover the rest).
            ChatbotQueryClassifier::SYSTEM_HELP,
            ChatbotQueryClassifier::GREETING,
            ChatbotQueryClassifier::THANKS,
            ChatbotQueryClassifier::OUT_OF_SCOPE,
            ChatbotQueryClassifier::ADVERSARIAL,
            ChatbotQueryClassifier::GIBBERISH,
            ChatbotQueryClassifier::EMOTIONAL,
            ChatbotQueryClassifier::IMPOSSIBLE_ACTION => ['courses' => [], 'notes' => []],
            // AMBIGUOUS and MULTI_QUESTION both genuinely need a real
            // search — AMBIGUOUS to find what a bare "comp"/"programming"
            // could actually mean (or come back empty for a truly vague
            // "courses"/"help", which the system prompt reads as "ask
            // what they mean" instead), MULTI_QUESTION because each of
            // its sub-questions still needs whatever course(s) it named
            // resolved — same textSearch() every other free-text category
            // already uses, just labelled differently for Groq.
            default => ['courses' => $this->textSearch($message, $history), 'notes' => []],
        };
    }

    // ------------------------------------------------------------------
    // C. Prerequisites & co-requisites
    // ------------------------------------------------------------------

    private function retrievePrerequisite(string $message, array $history = []): array
    {
        if (preg_match('/walang prerequisite|walang prereq|no prerequisite/i', $message)) {
            $courses = Course::query()
                ->where(fn (Builder $q) => $q->whereNull('prerequisite')->orWhere('prerequisite', ''))
                ->with('program')
                ->get();

            return [
                'courses' => $courses->map(fn (Course $c) => $this->formatCourseModel($c, 'prerequisite'))->all(),
                'notes' => ['These courses have no prerequisite listed in the system.'],
            ];
        }

        // "Ano-anong courses ang MAYROONG prerequisite?" — the mirror
        // image of the "walang prerequisite" branch above, which existed
        // ALONE; this one never did. Real bug (Rico, 2026-08-13): no
        // course in the current seed data actually has one (checked —
        // all 16 are null/empty), so this aggregate question genuinely
        // has a "wala" answer... but without this branch, the message
        // never even reached that correct conclusion honestly. It has no
        // code/title of its own to anchor to, so findAnchorCourses()
        // fell through to historyFallback() (see that method's docblock)
        // and kept re-anchoring on COMP 001 — the course that happened
        // to dominate recent turns purely because it's the one real
        // course with any uploaded syllabus data at all — then reported
        // on JUST that one course's own (null) prerequisite, repeatedly,
        // getting more confusing with every follow-up instead of just
        // answering the aggregate question that was actually asked.
        // Checked before the anchor logic below for the same reason the
        // "walang" branch is: this is a question about courses as a
        // GROUP, never about one specific named course.
        $namesNoCourse = !preg_match('/\b[A-Za-z]{2,6}\s?-?\s?\d{2,4}\b/', $message);

        // "most"/"pinakamaraming" excluded — "Which course HAS THE MOST
        // prerequisites?" also contains "has" + "prerequisites", but
        // it's a superlative single-course question, not this
        // existence-check one; that one still needs the anchor logic
        // below (well, textSearch()'s general fallback, since it has no
        // code either) rather than this aggregate list.
        if ($namesNoCourse
            && preg_match('/\b(may|mayroong?|existing|existed|has|have)\b/i', $message)
            && preg_match('/prerequisite|prereq/i', $message)
            && !preg_match('/\bmost\b|pinaka-?madami|pinaka-?maraming/i', $message)) {
            $courses = Course::query()
                ->whereNotNull('prerequisite')
                ->where('prerequisite', '!=', '')
                ->with('program')
                ->get();

            return [
                'courses' => $courses->map(fn (Course $c) => $this->formatCourseModel($c, 'prerequisite'))->all(),
                'notes' => $courses->isEmpty()
                    ? ['No courses in the system currently have a prerequisite listed — every course\'s prerequisite is blank/none.']
                    : ['These courses have a prerequisite listed in the system.'],
            ];
        }

        // Co-requisite's own existence-check pair — the same two-sided
        // gap as prerequisite's above, found by going through the
        // curriculum spreadsheets' actual columns (Rico, 2026-08-13) for
        // what other "which courses HAVE/HAVE NO X" questions this same
        // pattern applies to. PREREQUISITE already classifies co-
        // requisite questions too (see ChatbotQueryClassifier's own
        // 'co-req'/'corequisite' triggers), but retrieval only ever
        // handled a co-requisite in the context of one NAMED course —
        // never "which courses have one" as its own aggregate.
        if (preg_match('/walang co-?requisite|walang co requisite|no co-?requisite/i', $message)) {
            $courses = Course::query()
                ->where(fn (Builder $q) => $q->whereNull('corequisite')->orWhere('corequisite', ''))
                ->with('program')
                ->get();

            return [
                'courses' => $courses->map(fn (Course $c) => $this->formatCourseModel($c, 'prerequisite'))->all(),
                'notes' => ['These courses have no co-requisite listed in the system.'],
            ];
        }

        if ($namesNoCourse
            && preg_match('/\b(may|mayroong?|existing|existed|has|have)\b/i', $message)
            && preg_match('/co-?requisite|co requisite/i', $message)
            && !preg_match('/\bmost\b|pinaka-?madami|pinaka-?maraming/i', $message)) {
            $courses = Course::query()
                ->whereNotNull('corequisite')
                ->where('corequisite', '!=', '')
                ->with('program')
                ->get();

            return [
                'courses' => $courses->map(fn (Course $c) => $this->formatCourseModel($c, 'prerequisite'))->all(),
                'notes' => $courses->isEmpty()
                    ? ['No courses in the system currently have a co-requisite listed — every course\'s co-requisite is blank/none.']
                    : ['These courses have a co-requisite listed in the system.'],
            ];
        }

        $anchors = $this->findAnchorCourses($message, $history);

        // No specific course named — can't build a chain/reverse lookup
        // without an anchor. Fall back to plain text search so at least
        // something relevant surfaces instead of nothing.
        if ($anchors->isEmpty()) {
            return ['courses' => $this->textSearch($message), 'notes' => []];
        }

        $courses = collect();
        $notes = [];

        foreach ($anchors as $anchor) {
            $courses->push($anchor);

            // Forward: does the anchor's own prerequisite/corequisite
            // text resolve to a real course in the system? Include it
            // too so Groq can name it properly instead of just
            // echoing the raw text back.
            foreach (['prerequisite', 'corequisite'] as $field) {
                $value = trim((string) $anchor->{$field});

                if ($value === '') {
                    continue;
                }

                $resolved = $this->findAnchorCourses($value)->first();

                if ($resolved) {
                    $courses->push($resolved);
                } else {
                    $notes[] = "{$anchor->course_code}'s listed {$field} is \"{$value}\" — not found as its own course in the system, shown as entered.";
                }
            }

            // Reverse: which OTHER courses list this one as their
            // prerequisite/corequisite? ("what depends on COMP 003?")
            $dependents = Course::query()
                ->where('id', '!=', $anchor->id)
                ->where(fn (Builder $q) => $q
                    ->where('prerequisite', 'like', "%{$anchor->course_code}%")
                    ->orWhere('corequisite', 'like', "%{$anchor->course_code}%"))
                ->with('program')
                ->get();

            $courses = $courses->concat($dependents);
        }

        return [
            'courses' => $courses->unique('id')->map(fn (Course $c) => $this->formatCourseModel($c, 'prerequisite'))->values()->all(),
            'notes' => $notes,
        ];
    }

    // ------------------------------------------------------------------
    // I. Faculty/uploader queries
    // ------------------------------------------------------------------

    private function retrieveFacultyUploader(string $message, array $history = [], string $role = 'faculty'): array
    {
        // "How many recent uploads?" — a broad question, not about one
        // course. Guarded the same way as retrieveStats() (see its
        // docblock for the full "coincidental anchor" bug story) — a
        // message with no course-code-shaped token in it never
        // legitimately anchors, no matter what findAnchorCourses()'s
        // fallback layers might turn up.
        $namesNoCourse = !preg_match('/\b[A-Za-z]{2,6}\s?-?\s?\d{2,4}\b/', $message);
        $broadUploadQuestion = $namesNoCourse && (bool) preg_match('/\brecent\b|\blatest\b|\bilan\b|\bhow many\b/i', $message);

        $anchors = $broadUploadQuestion ? collect() : $this->findAnchorCourses($message, $history);
        $notes = [];

        if ($anchors->isNotEmpty()) {
            foreach ($anchors as $anchor) {
                $files = Syllabus::where('course_id', $anchor->id)->with('uploader')->get();

                if ($files->isEmpty()) {
                    $notes[] = "{$anchor->course_code} has no uploaded syllabus, so there is no uploader to report.";

                    continue;
                }

                foreach ($files as $file) {
                    $uploader = $file->uploader?->name ?? 'an unknown user';
                    $notes[] = "{$anchor->course_code}'s " . strtoupper($file->file_type) . " syllabus was uploaded by {$uploader} on " . $file->created_at->format('M j, Y') . '.';
                }
            }

            return [
                'courses' => $anchors->map(fn (Course $c) => $this->formatCourseModel($c, 'faculty_uploader'))->all(),
                'notes' => $notes,
            ];
        }

        // Per-course uploader lookup (the anchored branch above) stays
        // open to everyone — browsing a course and its syllabus files
        // is already public to every authenticated role (CLAUDE.md §7).
        // These two below are system-WIDE activity views — who's
        // uploaded the most, everything uploaded recently across the
        // whole curriculum — which the admin/intern dashboard already
        // keeps admin/intern-only (faculty's own dashboard only shows
        // courses THEY created), so Sage draws the same line rather
        // than handing out a system-wide activity feed through chat.
        if (!in_array($role, self::PRIVILEGED_ROLES, true)) {
            return $this->restrictedResponse('system-wide upload activity');
        }

        if (preg_match('/pinakamaraming/i', $message)) {
            $top = Syllabus::query()
                ->select('uploaded_by')
                ->selectRaw('COUNT(*) as upload_count')
                ->groupBy('uploaded_by')
                ->orderByDesc('upload_count')
                ->with('uploader')
                ->first();

            $notes[] = $top && $top->uploader
                ? "{$top->uploader->name} has uploaded the most syllabi ({$top->upload_count})."
                : 'No syllabi have been uploaded yet.';

            return ['courses' => [], 'notes' => $notes];
        }

        // "this month" / "recently" / "how many recent uploads" —
        // recent uploads generally. Leads with the actual total so a
        // "how many" question gets a real number, not just a list.
        $totalUploads = Syllabus::count();
        $notes[] = "There are {$totalUploads} syllabus files uploaded in total.";

        $recent = Syllabus::query()->with(['course', 'uploader'])->orderByDesc('created_at')->limit(10)->get();

        foreach ($recent as $file) {
            if (!$file->course) {
                continue;
            }

            $uploader = $file->uploader?->name ?? 'an unknown user';
            $notes[] = "{$file->course->course_code} — " . strtoupper($file->file_type) . " uploaded by {$uploader} on " . $file->created_at->format('M j, Y') . '.';
        }

        return ['courses' => [], 'notes' => $notes];
    }

    // ------------------------------------------------------------------
    // B. Syllabus availability
    // ------------------------------------------------------------------

    private function retrieveSyllabusAvailability(string $message, array $history = []): array
    {
        // "Can you give me all the courses that are in Curriculum
        // 2022-2023?" — added 2026-08-13 (Rico): curriculum_year is a
        // real column on syllabi, but nothing queried it before this,
        // so this genuinely had no answer anywhere — not a bug in the
        // sense of a wrong result, a real missing capability. Checked
        // first, before the single-course anchor logic below, since
        // "which courses are under year X" is inherently a filtered
        // list, never about one specific course.
        if (preg_match('/curriculum|school\s?year|academic\s?year|\bAY\b/i', $message)
            && ($curriculumYear = $this->extractCurriculumYear($message)) !== null) {
            $courses = Course::with('program')
                ->whereHas('syllabi', fn (Builder $q) => $q->where('curriculum_year', $curriculumYear))
                ->get();

            // A note explicitly ties the returned course(s) to the
            // curriculum year asked about — added 2026-08-13 after a
            // real flakiness bug: formatContextLine() (ChatbotService)
            // never mentions curriculum_year at all, only course_code/
            // title/hours/etc, so Gemini had no textual confirmation
            // that the course(s) it was given actually matched what was
            // asked. Same LIVE query, same result, sometimes answered
            // correctly and sometimes fell back to "wala akong nakita"
            // — the exact same non-determinism already fixed once for
            // an omitted "prerequisite: none" field (see
            // formatContextLine's docblock); this is that same class of
            // bug in a different spot, fixed the same way: state it
            // explicitly instead of leaving it implied.
            $notes = $courses->isEmpty()
                ? ["No courses have a syllabus filed under curriculum year {$curriculumYear}."]
                : ['The following courses have a syllabus filed under curriculum year ' . $curriculumYear . ': '
                    . $courses->pluck('course_code')->implode(', ') . '.'];

            return [
                'courses' => $courses->map(fn (Course $c) => $this->formatCourseModel($c, 'syllabus_availability'))->all(),
                'notes' => $notes,
            ];
        }

        // "Meron bang syllabus ang COMP 016?" — asking about ONE
        // specific course's availability, not a filtered list. Without
        // this check, a real bug (2026-08-12): the message doesn't
        // match any of the "wala pang"/"ilan"/"pinaka-complete" phrases
        // below, so it fell to the default "list courses WITH a
        // syllabus" branch — which, if COMP 016 has none, silently
        // excludes the very course being asked about, leaving Gemini
        // with an empty context and no way to answer "does IT have one".
        // "doesn't have"/"does not have"/"without a syllabus" added
        // 2026-08-13 — English equivalents of "wala pang"/"kulang" that
        // simply weren't recognized: "TELL ME ALL COURSES that doesn't
        // have syllabus yet" fell through every branch below (this
        // filterPhrase gate, then the "missing" branch itself) and
        // landed on the DEFAULT "courses WITH a syllabus" listing —
        // the exact opposite of what was asked.
        $filterPhrase = '/wala pang|kulang|missing|doesn\'?t have|does ?n\'?t have|don\'?t have|without (a |an )?syllabus|no syllabus|\bilan\b|\bhow many\b|\btotal\b|percentage|percent|pinaka-?complete|most complete/i';

        if (!preg_match($filterPhrase, $message)) {
            $anchors = $this->findAnchorCourses($message, $history);

            if ($anchors->isNotEmpty()) {
                return [
                    'courses' => $anchors->map(fn (Course $c) => $this->formatCourseModel($c, 'syllabus_availability'))->all(),
                    'notes' => [],
                ];
            }
        }

        $scope = $this->parseScope($message);

        if (preg_match('/pinaka-?complete|most complete/i', $message)) {
            $byYear = Course::query()
                ->selectRaw('year_level, COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN EXISTS (SELECT 1 FROM syllabi WHERE syllabi.course_id = courses.id AND syllabi.deleted_at IS NULL) THEN 1 ELSE 0 END) as with_syllabus')
                ->groupBy('year_level')
                ->orderByDesc('with_syllabus')
                ->get();

            $notes = $byYear->map(fn ($row) => "Year {$row->year_level}: {$row->with_syllabus}/{$row->total} courses have a syllabus.")->all();

            return ['courses' => [], 'notes' => $notes];
        }

        $base = Course::query()->with('program');
        $this->applyScope($base, $scope);

        if (preg_match('/wala pang|kulang|missing|doesn\'?t have|does ?n\'?t have|don\'?t have|without (a |an )?syllabus|no syllabus/i', $message)) {
            $courses = (clone $base)->whereDoesntHave('syllabi')->get();

            return [
                'courses' => $courses->map(fn (Course $c) => $this->formatCourseModel($c, 'syllabus_availability'))->all(),
                'notes' => [],
            ];
        }

        if (preg_match('/ilan|how many|total|percentage|percent/i', $message)) {
            $total = (clone $base)->count();
            $withSyllabus = (clone $base)->whereHas('syllabi')->count();
            $pct = $total > 0 ? round($withSyllabus / $total * 100, 1) : 0;

            return [
                'courses' => [],
                'notes' => ["{$withSyllabus} out of {$total} courses in scope have an uploaded syllabus ({$pct}%)."],
            ];
        }

        // Default: "may syllabus na" / "list lahat ng available" —
        // courses that DO have a syllabus.
        $courses = (clone $base)->whereHas('syllabi')->get();

        return [
            'courses' => $courses->map(fn (Course $c) => $this->formatCourseModel($c, 'syllabus_availability'))->all(),
            'notes' => [],
        ];
    }

    // ------------------------------------------------------------------
    // D. Year level & semester queries
    // ------------------------------------------------------------------

    private function retrieveYearSemester(string $message, array $history = []): array
    {
        $scope = $this->parseScope($message);

        // "Can you tell me the courses in year 2024?" — real bug
        // (2026-08-13): this curriculum's "year level" means 1st–4th
        // year of study, never a calendar year, but parseScope() only
        // recognizes ordinal forms (1st/2nd/3rd/4th/first/second/...) so
        // a bare "2024" silently resolves to no scope at all — and used
        // to then either wrongly anchor on an unrelated course (fixed
        // separately in findAnchorCourses(), see its docblock) or just
        // dump the entire unfiltered 16-course catalog with nothing
        // explaining why "2024" didn't actually filter anything. Neither
        // is what was asked. Caught here before either of those paths
        // even runs: a calendar-year-shaped number with no valid
        // ordinal-year scope found alongside it means the question
        // itself doesn't map onto this curriculum's concept of "year" —
        // say so plainly instead of guessing.
        if (!$scope['year'] && !$scope['semester'] && preg_match('/\b(19|20)\d{2}\b/', $message, $calendarYear)) {
            return [
                'courses' => [],
                'notes' => ["\"{$calendarYear[0]}\" is not a valid year level in this curriculum — year level here means 1st through 4th year of study (how far along in the program a course is taken), not a calendar year. There is no course data organized by calendar year."],
            ];
        }

        // "anong year level ang COMP 018?" / "anong semester ang Web
        // Development?" — asking about ONE course's placement, not a
        // filtered list, and no explicit year/sem filter was given.
        // !$scope['program'] added 2026-08-13 — real bug: "What about
        // the courses in DIT program?" explicitly names a whole
        // PROGRAM (a scope, not one course), but the anchor lookup
        // still ran anyway and coincidentally FULLTEXT-matched "program"
        // as a prefix of "Programming" in three unrelated BSIT courses
        // — then returned exactly THOSE three, ignoring the DIT scope
        // entirely. Naming a whole program is exactly as strong a
        // "this is a group/scope question" signal as naming a year or
        // semester already was, so it's guarded the same way.
        $anchors = $this->findAnchorCourses($message, $history);

        if ($anchors->isNotEmpty() && !$scope['year'] && !$scope['semester'] && !$scope['program']) {
            return [
                'courses' => $anchors->map(fn (Course $c) => $this->formatCourseModel($c, 'year_semester'))->all(),
                'notes' => [],
            ];
        }

        $query = Course::query()->with('program');
        $this->applyScope($query, $scope);
        $courses = $query->orderBy('year_level')->orderBy('semester')->get();

        $notes = [];
        if (preg_match('/ilan|how many/i', $message)) {
            $notes[] = 'Total courses matching this year/semester scope: ' . $courses->count() . '.';
        }

        return [
            'courses' => $courses->map(fn (Course $c) => $this->formatCourseModel($c, 'year_semester'))->all(),
            'notes' => $notes,
        ];
    }

    // ------------------------------------------------------------------
    // E. Units & hours
    // ------------------------------------------------------------------

    private function retrieveStats(string $message, array $history = [], string $role = 'faculty'): array
    {
        // Admin/system-meta counts — NOT about courses at all, so these
        // are checked first and return immediately. Real gap (Rico,
        // 2026-08-13): "How many faculty accounts are existing?" and "I
        // mean, how many course change request?" both classify as STATS
        // (they say "how many"), but retrieveStats() had no idea what to
        // do with them and fell through to the generic course-count
        // aggregate below — answering with a curriculum course count
        // that has nothing to do with what was actually asked. Gemini's
        // own grounding check caught the mismatch and refused rather
        // than passing along a wrong number, but "wala akong nakita"
        // for a perfectly legitimate question is still wrong — these
        // ARE real, queryable facts, just not curriculum ones.
        //
        // Both are also PRIVILEGED — restricted to admin/intern (Rico,
        // 2026-08-13, second request: "dapat magkaiba ang information...
        // kay admin/intern sa faculty accounts to protect the system").
        // The check happens BEFORE the actual count query even runs, and
        // the real number never gets computed for a disallowed role —
        // this is enforced here in PHP, not by asking Groq nicely not
        // to repeat it, so there's no prompt-engineering trick that gets
        // it to leak the real figure to a Faculty account.
        if (preg_match('/faculty (account|user)s?|mga faculty account/i', $message)) {
            return ['courses' => [], 'notes' => ['Faculty accounts have been removed from this system.']];
        }

        if (preg_match('/change request|edit request|request(s)? to edit|pending (na )?request/i', $message)) {
            return ['courses' => [], 'notes' => ['Change requests have been removed from this system.']];
        }

        // "Ilang units ang COMP 016?" — a specific course's own
        // numbers, not an aggregate. Only treat this as a curriculum-
        // wide aggregate when no course is actually being named.
        //
        // "Generic aggregate" phrasing skips the anchor lookup entirely
        // rather than trusting whatever findAnchorCourses() returns —
        // real bug (2026-08-13, two different cases): "How many courses
        // are existing sa system?" has no named course at all, but its
        // keyword fallback (see textSearch()) FULLTEXT-matched the
        // standalone word "system" as a prefix of "Systems" in three
        // unrelated titles — a coincidental collision. Separately, "If
        // so, how many courses are in BSIT?" has no code/title of its
        // own either, but historyFallback() picked up a course code
        // mentioned several turns earlier in the SAME conversation and
        // anchored to THAT instead. Both are the same underlying mistake
        // — treating any collection-counting question ("how many/ilan
        // ...course(s)...") as if it named one specific course — so
        // both are guarded the same way: skip anchoring entirely
        // whenever the message is asking to count "course(s)" as a
        // group, regardless of which fallback layer would have produced
        // the false anchor.
        // "number of" and "existed" added 2026-08-13 (second round) —
        // same drift risk flagged elsewhere: this guard's own trigger
        // words have to stay in sync with ChatbotQueryClassifier's STATS
        // trigger, or a message that gets correctly classified as STATS
        // can still slip past THIS guard and anchor wrongly anyway. "the
        // number of courses existed in the system" is exactly that
        // case — classifies as STATS fine, but neither "ilan"/"how many"
        // nor "existing" (only "existed") were recognized here, so it
        // still tried to anchor and answered with a coincidental
        // FULLTEXT-prefix match on "system" (4 courses) instead of the
        // true total (16).
        $countsCoursesAsGroup = preg_match('/\b(subjects?|courses?)\b/i', $message)
            && preg_match('/\bilan\b|\bhow many\b|\bnumber of\b/i', $message);

        $genericAggregate = $countsCoursesAsGroup
            || (bool) preg_match('/\btotal\b|\bexist(ing|ed)\b|\blahat\b|\bbuong\b|\bkabuuan\b|\boverall\b/i', $message);

        $anchors = $genericAggregate ? collect() : $this->findAnchorCourses($message, $history);

        if ($anchors->isNotEmpty()) {
            return [
                'courses' => $anchors->map(fn (Course $c) => $this->formatCourseModel($c, 'stats'))->all(),
                'notes' => [],
            ];
        }

        $scope = $this->parseScope($message);
        $query = Course::query()->with('program');
        $this->applyScope($query, $scope);
        $courses = $query->get();

        $notes = [
            'Course count in scope: ' . $courses->count() . '.',
            'Total credited units in scope: ' . $courses->sum('credited_units') . '.',
            'Total lecture hours in scope: ' . $courses->sum('lecture_hours') . '.',
            'Total lab hours in scope: ' . $courses->sum('lab_hours') . '.',
            // Added 2026-08-13 going through the curriculum spreadsheets'
            // actual columns (Rico) — tuition_hours is a real, tracked
            // field (already surfaced per-course in formatContextLine),
            // but the aggregate totals here never summed it, so "total
            // tuition hours" questions had no aggregate answer at all.
            'Total tuition hours in scope: ' . $courses->sum('tuition_hours') . '.',
        ];

        if (preg_match('/pinakamataas|highest/i', $message)) {
            $top = $courses->sortByDesc('credited_units')->first();

            if ($top) {
                $notes[] = "Highest credited-units course in scope: {$top->course_code} — {$top->title} ({$top->credited_units} units).";
            }
        }

        if (preg_match('/pinakamabigat|heaviest/i', $message)) {
            $bySemesterUnits = $courses->groupBy(fn (Course $c) => "Year {$c->year_level} {$c->semester} sem")
                ->map(fn (Collection $group) => (float) $group->sum('credited_units'))
                ->sortDesc();

            if ($bySemesterUnits->isNotEmpty()) {
                $notes[] = 'Total credited units per year/semester (heaviest first): '
                    . $bySemesterUnits->map(fn ($units, $label) => "{$label}: {$units}")->implode(', ') . '.';
            }
        }

        // Also hand over the individual courses in scope, not just the
        // aggregate notes above — a curriculum-sized list is cheap to
        // include, and some "stats" questions are really judgment calls
        // over titles ("Ilan ang programming-related courses?") that
        // Groq can only make if it can see them, not just a total.
        return [
            'courses' => $courses->map(fn (Course $c) => $this->formatCourseModel($c, 'stats'))->all(),
            'notes' => $notes,
        ];
    }

    // ------------------------------------------------------------------
    // F. Program comparison
    // ------------------------------------------------------------------

    private function retrieveProgramComparison(): array
    {
        $programs = Program::with('courses')->get();
        $notes = [];

        foreach ($programs as $program) {
            $courses = $program->courses;
            $notes[] = "{$program->code} ({$program->name}): {$courses->count()} courses, "
                . $courses->sum('credited_units') . ' total credited units, '
                . $courses->pluck('year_level')->filter()->unique()->count() . ' year levels represented.';
        }

        // Title-based set difference — course codes differ by program
        // prefix (COMP vs DIT) by design, so code comparison would be
        // meaningless; title is what actually answers "which courses
        // are in one program but not the other".
        $bsit = $programs->firstWhere('code', 'BSIT');
        $dit = $programs->firstWhere('code', 'DIT');

        if ($bsit && $dit) {
            $bsitTitles = $bsit->courses->pluck('title')->map(fn (string $t) => mb_strtolower($t));
            $ditTitles = $dit->courses->pluck('title')->map(fn (string $t) => mb_strtolower($t));

            $onlyBsit = $bsit->courses->reject(fn (Course $c) => $ditTitles->contains(mb_strtolower($c->title)));
            $onlyDit = $dit->courses->reject(fn (Course $c) => $bsitTitles->contains(mb_strtolower($c->title)));
            $shared = $bsit->courses->filter(fn (Course $c) => $ditTitles->contains(mb_strtolower($c->title)));

            if ($onlyBsit->isNotEmpty()) {
                $notes[] = 'Courses only in BSIT: ' . $onlyBsit->pluck('title')->implode(', ') . '.';
            }
            if ($onlyDit->isNotEmpty()) {
                $notes[] = 'Courses only in DIT: ' . $onlyDit->pluck('title')->implode(', ') . '.';
            }
            if ($shared->isNotEmpty()) {
                $notes[] = 'Courses offered in both programs (matched by title): ' . $shared->pluck('title')->implode(', ') . '.';
            }
        }

        return ['courses' => [], 'notes' => $notes];
    }

    // ------------------------------------------------------------------
    // G. Course type/category queries
    // ------------------------------------------------------------------

    private function retrieveCourseCategory(string $message): array
    {
        $lower = mb_strtolower($message);
        $query = Course::query()->with('program');
        $matchedPrefix = null;

        if (str_contains($lower, 'geed')) {
            $query->where('course_code', 'like', 'GEED%');
        } elseif (str_contains($lower, 'nstp')) {
            $query->where(fn (Builder $q) => $q->where('course_code', 'like', 'NSTP%')->orWhere('title', 'like', '%NSTP%'));
        } elseif (str_contains($lower, 'pathfit')) {
            $query->where(fn (Builder $q) => $q->where('course_code', 'like', 'PATHFIT%')->orWhere('title', 'like', '%PATHFIT%'));
        } elseif (str_contains($lower, 'elective')) {
            $query->where('title', 'like', '%elective%');
        } elseif (preg_match('/purely lecture|walang lab/i', $message)) {
            $query->where(fn (Builder $q) => $q->where('lab_hours', 0)->orWhereNull('lab_hours'))->where('lecture_hours', '>', 0);
        } elseif (str_contains($lower, 'may lab') || str_contains($lower, 'laboratory component')) {
            $query->where('lab_hours', '>', 0);
        } elseif (str_contains($lower, 'accounting')) {
            $query->where('title', 'like', '%accounting%');
        } elseif ($matchedPrefix = $this->findMentionedCodePrefix($message)) {
            // "Can you tell me all the courses that starts with the
            // course code 'COMP'?" / "ALL COMP course code?" — real
            // bug (Rico, 2026-08-13): no capability anywhere queried by
            // prefix generically (only the three hardcoded GEED/NSTP/
            // PATHFIT ones above did), so this fell all the way to
            // GENERAL_SEARCH, whose keywordFallback() deliberately SKIPS
            // shared prefixes like "comp" (see its own docblock — a
            // real, different bug fixed 2026-08-12 by excluding them),
            // leaving nothing behind except a stale historyFallback()
            // match on whatever course had dominated recent
            // conversation — one wrong course instead of all thirteen.
            // Computed from the actual data, not hardcoded, so it stays
            // correct as the curriculum grows.
            $query->where('course_code', 'like', "{$matchedPrefix}%");
        }

        $courses = $query->get();

        return [
            'courses' => $courses->map(fn (Course $c) => $this->formatCourseModel($c, 'course_category'))->all(),
            'notes' => [],
        ];
    }

    /**
     * Any REAL course_code prefix (COMP, DIT, GEED, ...) mentioned in
     * the message as its own word — used to answer "all X courses" /
     * "X course code" / "starts with X" generically, for whichever
     * prefix is actually being asked about, not just the three special-
     * cased category keywords above.
     */
    private function findMentionedCodePrefix(string $message): ?string
    {
        $prefixes = Course::query()
            ->pluck('course_code')
            ->map(fn (string $code) => strtolower(trim(preg_replace('/[^A-Za-z].*$/', '', $code))))
            ->filter(fn (string $prefix) => $prefix !== '')
            ->unique();

        foreach ($prefixes as $prefix) {
            if (preg_match('/\b' . preg_quote($prefix, '/') . '\b/i', $message)) {
                return strtoupper($prefix);
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // A/H/general — text search (reuses SearchController, no duplicated
    // FULLTEXT/fuzzy logic)
    // ------------------------------------------------------------------

    /**
     * SearchController::performSearch() is tuned for the plain search
     * box: short, keyword-style input. Its FULLTEXT layer requires
     * EVERY token to match (boolean AND), and its fuzzy fallback scores
     * the query as one whole string — both assumptions break on a full
     * conversational sentence, which mostly won't match anything even
     * though a key phrase in it clearly should.
     *
     * So: try the raw message first (covers a faculty member who just
     * types keywords straight into the chat, same as the search box —
     * "comp", "web dev", "capstone", "networking", "rizal"). If that
     * finds nothing, strip filler words and retry per significant
     * keyword instead (FULLTEXT-only, no fuzzy — see keywordFallback()).
     * If THAT still finds nothing and there's conversation history, a
     * pronoun follow-up ("Ilan ang credit units nito?") gets one more
     * try anchored to whatever course was named in recent turns — see
     * historyFallback().
     *
     * @return array<int, array<string, mixed>>
     */
    public function textSearch(string $message, array $history = []): array
    {
        $aliased = $this->resolveAliasedCourse($message);

        if ($aliased !== null) {
            return [$aliased];
        }

        $results = $this->search->performSearch($message);

        if (!empty($results)) {
            return $results;
        }

        $results = $this->keywordFallback($message);

        if (!empty($results)) {
            return $results;
        }

        // historyFallback() is meant for genuine referential follow-ups
        // ("Ilan ang units NITO?" — "it" means whatever was just
        // discussed) — never appropriate for a message that's plainly
        // asking about a GROUP of courses, which "all"/"lahat"/
        // "overall" is an unambiguous signal of. Real bug (2026-08-13):
        // "Can you tell me all the courses that starts with the course
        // code 'COMP'?" kept re-anchoring on whichever ONE course had
        // dominated recent conversation instead of correctly finding
        // none (this specific case is now its own real capability — see
        // ChatbotRetrievalService::findMentionedCodePrefix() — but the
        // underlying historyFallback overreach is general, not unique
        // to that one phrasing, so it's guarded here too). "overall"
        // added after "What about overall 1st courses?" slipped past
        // the original "all"/"lahat"-only guard — \ball\b requires a
        // WORD boundary, which "overall" doesn't have around its "all".
        if (preg_match('/\ball\b|\blahat\b|\boverall\b/i', $message)) {
            return [];
        }

        return $this->historyFallback($history);
    }

    /**
     * Common IT-curriculum shorthand faculty actually type, mapped to
     * the ONE canonical course title it should resolve to — not just
     * "improves" the match, but pins it to exactly one course. Without
     * this (Rico, 2026-08-13): "OOP" matched nothing at all — no course
     * title has a word literally starting with "oop" for the FULLTEXT
     * prefix search to catch — and "Web Dev" matched BOTH "Web
     * Development" (COMP 016) AND "Advanced Web and Mobile Development"
     * (DIT 103), since FULLTEXT boolean mode requires each word present
     * ANYWHERE in the title, not adjacently — both titles contain both
     * "Web" and "Development" as separate words. Textually expanding
     * "web dev" into "Web Development" and re-running it through that
     * same AND search doesn't fix that (tried first, still matched both)
     * — so this looks the course up directly by its exact title
     * instead, which is unambiguous by construction.
     *
     * Deliberately small and curated, not an exhaustive dictionary — add
     * more here as real gaps turn up, same spirit as this file's
     * STOPWORDS list. Word-boundary matched so "oop" only fires on the
     * standalone token, never a substring inside an unrelated word.
     */
    private const COURSE_ALIASES = [
        'oop' => 'Object Oriented Programming',
        'web dev' => 'Web Development',
        'dsa' => 'Data Structures and Algorithms',
        'dbms' => 'Database Management Systems',
    ];

    private function resolveAliasedCourse(string $message): ?array
    {
        foreach (self::COURSE_ALIASES as $alias => $canonicalTitle) {
            if (!preg_match('/\b' . preg_quote($alias, '/') . '\b/i', $message)) {
                continue;
            }

            $course = Course::with('program')->where('title', $canonicalTitle)->first();

            if ($course) {
                return $this->formatCourseModel($course, 'alias');
            }
        }

        return null;
    }

    /**
     * Resolves a text fragment (a course code, or a title-ish phrase)
     * to actual Course models — used by the structured strategies above
     * to find the "anchor" course a question is really about.
     *
     * Falls back to $history when $text alone resolves to nothing — a
     * pronoun follow-up ("Ilan ang credit units NITO?") has no
     * identifying detail of its own, so every structured strategy that
     * needs an anchor (prerequisite, faculty_uploader, year_semester,
     * stats) gets the same "look at what was just discussed" recovery
     * general_search/course_lookup/syllabus_content already had via
     * textSearch()'s own historyFallback() call — this just extends it
     * to also cover a message whose FIRST textSearch() attempt (with no
     * history involved yet) truly found nothing.
     */
    private function findAnchorCourses(string $text, array $history = []): Collection
    {
        // SYLLABUS CONTENT matches are NEVER trusted as an anchor here,
        // full stop — not just downgraded when a stronger match also
        // exists (tried that first, 2026-08-13; still broke). Every
        // caller of findAnchorCourses() (prerequisite, faculty_uploader,
        // syllabus_availability, year_semester, stats) is asking "did
        // the user name ONE specific course", and a match that only
        // comes from an unrelated uploaded file's extracted TEXT
        // happening to contain a matching word is never a real answer to
        // that — it's always a coincidence, since nobody names a course
        // by quoting a phrase from inside its syllabus. (SYLLABUS_CONTENT
        // itself is unaffected — that type never calls this method, it
        // reads textSearch() directly, where content matches are exactly
        // the point.) Second real bug of this exact shape (2026-08-13):
        // "Can you tell me the courses in year 2024?" anchored on COMP
        // 001 purely because its uploaded file's text happened to
        // contain "2024" somewhere — the only match found at all, so the
        // earlier "only when competing with a stronger match" guard
        // never even triggered.
        $results = collect($this->textSearch($text))
            ->reject(fn (array $r) => $r['match_type'] === 'syllabus_content');

        $ids = $results->pluck('course_id');

        // Same "all"/"lahat"/"overall" guard as textSearch()'s own
        // historyFallback call just below it — this is a SEPARATE
        // fallback path (this method calls textSearch($text) with no
        // $history above, so that guard alone doesn't cover this one).
        if ($ids->isEmpty() && !empty($history) && !preg_match('/\ball\b|\blahat\b|\boverall\b/i', $text)) {
            $ids = collect($this->historyFallback($history))->pluck('course_id');
        }

        if ($ids->isEmpty()) {
            return collect();
        }

        return Course::whereIn('id', $ids)->with('program')->get()
            ->sortBy(fn (Course $c) => $ids->search($c->id))
            ->values();
    }

    /**
     * Per-keyword retry, FULLTEXT-only (no fuzzy). One bug this already
     * burned us on (2026-08-12): a generic conversational word ("credit",
     * "ilan", "send") let through the PHP fuzzy fallback can
     * coincidentally clear the similarity threshold against an unrelated
     * course — worse than finding nothing. Also skips known shared code
     * prefixes (see sharedCodePrefixes()) — splitting "COMP 001" into
     * "comp" and "001" and searching "comp" ALONE matches nearly the
     * entire BSIT catalog, since it's a complete word shared by every
     * BSIT course's code.
     *
     * @return array<int, array<string, mixed>>
     */
    private function keywordFallback(string $text): array
    {
        $skip = $this->sharedCodePrefixes();
        $merged = [];

        foreach ($this->extractKeywords($text) as $keyword) {
            if (in_array($keyword, $skip, true)) {
                continue;
            }

            foreach ($this->search->performSearch($keyword, allowFuzzy: false) as $result) {
                $id = $result['course_id'];

                if (!isset($merged[$id]) || $result['score'] > $merged[$id]['score']) {
                    $merged[$id] = $result;
                }
            }
        }

        $results = array_values($merged);
        usort($results, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, 8);
    }

    /**
     * The course being discussed is almost always still named by code
     * somewhere in the last few turns — our own replies always mention
     * it — so re-scanning recent history for something code-shaped and
     * searching THAT re-anchors a pronoun follow-up to the right course
     * instead of leaving it ungrounded.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<int, array<string, mixed>>
     */
    private function historyFallback(array $history): array
    {
        if (empty($history)) {
            return [];
        }

        $recentText = collect($history)->slice(-4)->pluck('content')->implode(' ');

        foreach ($this->extractCourseCodeLikeTokens($recentText) as $code) {
            $results = $this->search->performSearch($code);

            if (!empty($results)) {
                return $results;
            }
        }

        return [];
    }

    /** @return string[] */
    private function extractKeywords(string $message): array
    {
        preg_match_all('/[\p{L}0-9]+/u', mb_strtolower($message), $matches);

        return array_values(array_filter(
            array_unique($matches[0]),
            fn (string $word) => mb_strlen($word) >= 3 && !in_array($word, self::STOPWORDS, true)
        ));
    }

    /**
     * Alpha prefixes (e.g. "comp", "dit") shared by 2+ course codes —
     * see keywordFallback()'s docblock for why these must never be
     * searched alone. Computed from the actual data rather than
     * hardcoded, so it stays correct if a new program/prefix is added.
     *
     * @return string[]
     */
    private function sharedCodePrefixes(): array
    {
        return Course::query()
            ->pluck('course_code')
            ->map(fn (string $code) => strtolower(trim(preg_replace('/[^A-Za-z].*$/', '', $code))))
            ->filter(fn (string $prefix) => $prefix !== '')
            ->countBy()
            ->filter(fn (int $count) => $count > 1)
            ->keys()
            ->all();
    }

    /**
     * Course codes in this curriculum are a short letter prefix plus a
     * number, with or without a space ("COMP 001", "COMP001", "DIT 101").
     *
     * @return string[]
     */
    private function extractCourseCodeLikeTokens(string $text): array
    {
        preg_match_all('/\b[A-Za-z]{2,6}\s?-?\s?\d{2,4}\b/', $text, $matches);

        return array_values(array_unique($matches[0]));
    }

    /**
     * Normalizes whatever year the user typed to the exact "YYYY-YYYY"
     * format curriculum_year is actually stored in (see
     * SyllabusController::curriculumYearOptions()) — "2022-2023" as-is,
     * "2022-23" expanded to the full second year, or a bare "2022"
     * assumed to mean the academic year starting then.
     */
    private function extractCurriculumYear(string $message): ?string
    {
        if (preg_match('/(\d{4})\s*-\s*(\d{2,4})/', $message, $m)) {
            $end = mb_strlen($m[2]) === 2 ? substr($m[1], 0, 2) . $m[2] : $m[2];

            return "{$m[1]}-{$end}";
        }

        if (preg_match('/\b(\d{4})\b/', $message, $m)) {
            return "{$m[1]}-" . ((int) $m[1] + 1);
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------

    /**
     * The access-controlled reply for a Faculty account asking for
     * something PRIVILEGED_ROLES-only. `courses`/`notes` stay empty on
     * purpose — the real data (the actual count, the actual names) is
     * never computed for this role in the first place, so there is
     * nothing for this note, or Groq, to leak even by accident. The
     * marker prefix is a private wire format between here and
     * ChatbotService::formatContext() — see its handling of it — not
     * something meant to reach the user verbatim.
     *
     * @param  string  $what  a bare noun phrase (e.g. "faculty account
     *                        details") — ChatbotService::accessRestrictedNote()
     *                        wraps it into a full sentence, so this must
     *                        NOT itself end in "is restricted to..." or
     *                        any other trailing clause.
     * @return array{courses: array<int, array<string, mixed>>, notes: string[]}
     */
    private function restrictedResponse(string $what): array
    {
        return ['courses' => [], 'notes' => ["ACCESS_RESTRICTED: {$what}"]];
    }

    /** @return array{program: ?string, year: ?int, semester: ?string} */
    private function parseScope(string $message): array
    {
        $lower = mb_strtolower($message);

        $program = null;
        if (str_contains($lower, 'bsit')) {
            $program = 'BSIT';
        } elseif (str_contains($lower, 'dit')) {
            $program = 'DIT';
        }

        // "...(year|courses?)" — not just "year", added 2026-08-13 after
        // "What about overall 1st courses?" resolved to no scope at
        // all ("1st" alone, with no "year"/"sem" suffix, matched
        // nothing) and fell through to anchor/history guessing instead
        // of being read as "1st year courses", which is clearly what
        // was meant given this app is never about anything BUT courses.
        // (subjects? kept alongside courses? — a user typing the old
        // term should filter exactly the same way.)
        $year = match (true) {
            (bool) preg_match('/\b(1st|first)\s?(year|courses?|subjects?)\b/i', $message) => 1,
            (bool) preg_match('/\b(2nd|second)\s?(year|courses?|subjects?)\b/i', $message) => 2,
            (bool) preg_match('/\b(3rd|third)\s?(year|courses?|subjects?)\b/i', $message) => 3,
            (bool) preg_match('/\b(4th|fourth)\s?(year|courses?|subjects?)\b/i', $message) => 4,
            default => null,
        };

        $semester = match (true) {
            (bool) preg_match('/\b1st\s?sem(ester)?\b/i', $message) => '1st',
            (bool) preg_match('/\b2nd\s?sem(ester)?\b/i', $message) => '2nd',
            str_contains($lower, 'summer') => 'summer',
            default => null,
        };

        return compact('program', 'year', 'semester');
    }

    private function applyScope(Builder $query, array $scope): void
    {
        if ($scope['program']) {
            $query->whereHas('program', fn (Builder $q) => $q->where('code', $scope['program']));
        }

        if ($scope['year']) {
            $query->where('year_level', $scope['year']);
        }

        if ($scope['semester']) {
            $query->where('semester', $scope['semester']);
        }
    }

    private function formatCourseModel(Course $course, string $matchType): array
    {
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
            'score' => 100.0,
        ];
    }
}
