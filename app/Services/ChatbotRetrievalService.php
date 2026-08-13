<?php

namespace App\Services;

use App\Http\Controllers\SearchController;
use App\Models\Program;
use App\Models\Subject;
use App\Models\SubjectChangeRequest;
use App\Models\Syllabus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * RAG retrieval for "Sage" — given a message already classified
 * by ChatbotQueryClassifier, runs the DB query that actually answers that
 * TYPE of question and hands back structured context for ChatbotService
 * to hand to Gemini. Per Rico, 2026-08-12 (chatbot-test-cases.md):
 * different question shapes need genuinely different queries, not just a
 * text search — "Ano ang prereq ng COMP 003?" needs a join/chain lookup,
 * "Ilan ang total units ng BSIT?" needs an aggregate, etc.
 *
 * Every retrieve*() method returns:
 *   ['subjects' => array<subject-shaped row>, 'notes' => string[]]
 * `subjects` rows share the exact field set SearchController::
 * formatSubject() already returns (subject_id, subject_code, title,
 * program, year_level, semester, prerequisite, corequisite,
 * lecture_hours, lab_hours, credited_units, tuition_hours, match_type) —
 * one consistent shape regardless of which strategy produced it, so
 * ChatbotService doesn't need to branch on query type downstream.
 * `notes` carries anything that isn't really "a subject" — an aggregate
 * total, a comparison summary, an uploader/date fact — as plain English
 * sentences appended to the prompt context alongside the subject list.
 *
 * Text-search categories (subject_lookup, general_search,
 * syllabus_content) reuse SearchController::performSearch() rather than
 * duplicating its FULLTEXT/fuzzy logic — see textSearch()'s docblock.
 * Everything else here is new structured querying that has no equivalent
 * in SearchController at all.
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
     * ONLY roles allowed to see faculty-account counts, subject change
     * request details, or system-wide upload activity via Sage. Mirrors
     * the exact same boundary the rest of the app already draws
     * (CLAUDE.md §7/§8: admin/intern manage faculty accounts and review
     * change requests; the admin/intern dashboard shows system-wide
     * recent uploads, faculty's dashboard only shows their own subjects)
     * — Sage isn't inventing a new rule, just not accidentally handing
     * out through chat what the UI itself already keeps admin/intern-only.
     */
    private const PRIVILEGED_ROLES = ['admin', 'intern'];

    /**
     * @param  string  $type  a ChatbotQueryClassifier::* constant
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  string  $role  the ASKING user's account role (admin/faculty/intern)
     *                        — deliberately defaults to the least-privileged
     *                        value so a caller that forgets to pass it fails
     *                        closed (denies access) rather than open.
     * @return array{subjects: array<int, array<string, mixed>>, notes: string[]}
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
            ChatbotQueryClassifier::SUBJECT_CATEGORY => $this->retrieveSubjectCategory($message),
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
            ChatbotQueryClassifier::IMPOSSIBLE_ACTION => ['subjects' => [], 'notes' => []],
            // AMBIGUOUS and MULTI_QUESTION both genuinely need a real
            // search — AMBIGUOUS to find what a bare "comp"/"programming"
            // could actually mean (or come back empty for a truly vague
            // "subjects"/"help", which the system prompt reads as "ask
            // what they mean" instead), MULTI_QUESTION because each of
            // its sub-questions still needs whatever subject(s) it named
            // resolved — same textSearch() every other free-text category
            // already uses, just labelled differently for Gemini.
            default => ['subjects' => $this->textSearch($message, $history), 'notes' => []],
        };
    }

    // ------------------------------------------------------------------
    // C. Prerequisites & co-requisites
    // ------------------------------------------------------------------

    private function retrievePrerequisite(string $message, array $history = []): array
    {
        if (preg_match('/walang prerequisite|walang prereq|no prerequisite/i', $message)) {
            $subjects = Subject::query()
                ->where(fn (Builder $q) => $q->whereNull('prerequisite')->orWhere('prerequisite', ''))
                ->with('program')
                ->get();

            return [
                'subjects' => $subjects->map(fn (Subject $s) => $this->formatSubjectModel($s, 'prerequisite'))->all(),
                'notes' => ['These subjects have no prerequisite listed in the system.'],
            ];
        }

        // "Ano-anong subjects ang MAYROONG prerequisite?" — the mirror
        // image of the "walang prerequisite" branch above, which existed
        // ALONE; this one never did. Real bug (Rico, 2026-08-13): no
        // subject in the current seed data actually has one (checked —
        // all 16 are null/empty), so this aggregate question genuinely
        // has a "wala" answer... but without this branch, the message
        // never even reached that correct conclusion honestly. It has no
        // code/title of its own to anchor to, so findAnchorSubjects()
        // fell through to historyFallback() (see that method's docblock)
        // and kept re-anchoring on COMP 001 — the subject that happened
        // to dominate recent turns purely because it's the one real
        // subject with any uploaded syllabus data at all — then reported
        // on JUST that one subject's own (null) prerequisite, repeatedly,
        // getting more confusing with every follow-up instead of just
        // answering the aggregate question that was actually asked.
        // Checked before the anchor logic below for the same reason the
        // "walang" branch is: this is a question about subjects as a
        // GROUP, never about one specific named subject.
        $namesNoSubject = !preg_match('/\b[A-Za-z]{2,6}\s?-?\s?\d{2,4}\b/', $message);

        // "most"/"pinakamaraming" excluded — "Which subject HAS THE MOST
        // prerequisites?" also contains "has" + "prerequisites", but
        // it's a superlative single-subject question, not this
        // existence-check one; that one still needs the anchor logic
        // below (well, textSearch()'s general fallback, since it has no
        // code either) rather than this aggregate list.
        if ($namesNoSubject
            && preg_match('/\b(may|mayroong?|existing|existed|has|have)\b/i', $message)
            && preg_match('/prerequisite|prereq/i', $message)
            && !preg_match('/\bmost\b|pinaka-?madami|pinaka-?maraming/i', $message)) {
            $subjects = Subject::query()
                ->whereNotNull('prerequisite')
                ->where('prerequisite', '!=', '')
                ->with('program')
                ->get();

            return [
                'subjects' => $subjects->map(fn (Subject $s) => $this->formatSubjectModel($s, 'prerequisite'))->all(),
                'notes' => $subjects->isEmpty()
                    ? ['No subjects in the system currently have a prerequisite listed — every subject\'s prerequisite is blank/none.']
                    : ['These subjects have a prerequisite listed in the system.'],
            ];
        }

        // Co-requisite's own existence-check pair — the same two-sided
        // gap as prerequisite's above, found by going through the
        // curriculum spreadsheets' actual columns (Rico, 2026-08-13) for
        // what other "which subjects HAVE/HAVE NO X" questions this same
        // pattern applies to. PREREQUISITE already classifies co-
        // requisite questions too (see ChatbotQueryClassifier's own
        // 'co-req'/'corequisite' triggers), but retrieval only ever
        // handled a co-requisite in the context of one NAMED subject —
        // never "which subjects have one" as its own aggregate.
        if (preg_match('/walang co-?requisite|walang co requisite|no co-?requisite/i', $message)) {
            $subjects = Subject::query()
                ->where(fn (Builder $q) => $q->whereNull('corequisite')->orWhere('corequisite', ''))
                ->with('program')
                ->get();

            return [
                'subjects' => $subjects->map(fn (Subject $s) => $this->formatSubjectModel($s, 'prerequisite'))->all(),
                'notes' => ['These subjects have no co-requisite listed in the system.'],
            ];
        }

        if ($namesNoSubject
            && preg_match('/\b(may|mayroong?|existing|existed|has|have)\b/i', $message)
            && preg_match('/co-?requisite|co requisite/i', $message)
            && !preg_match('/\bmost\b|pinaka-?madami|pinaka-?maraming/i', $message)) {
            $subjects = Subject::query()
                ->whereNotNull('corequisite')
                ->where('corequisite', '!=', '')
                ->with('program')
                ->get();

            return [
                'subjects' => $subjects->map(fn (Subject $s) => $this->formatSubjectModel($s, 'prerequisite'))->all(),
                'notes' => $subjects->isEmpty()
                    ? ['No subjects in the system currently have a co-requisite listed — every subject\'s co-requisite is blank/none.']
                    : ['These subjects have a co-requisite listed in the system.'],
            ];
        }

        $anchors = $this->findAnchorSubjects($message, $history);

        // No specific subject named — can't build a chain/reverse lookup
        // without an anchor. Fall back to plain text search so at least
        // something relevant surfaces instead of nothing.
        if ($anchors->isEmpty()) {
            return ['subjects' => $this->textSearch($message), 'notes' => []];
        }

        $subjects = collect();
        $notes = [];

        foreach ($anchors as $anchor) {
            $subjects->push($anchor);

            // Forward: does the anchor's own prerequisite/corequisite
            // text resolve to a real subject in the system? Include it
            // too so Gemini can name it properly instead of just
            // echoing the raw text back.
            foreach (['prerequisite', 'corequisite'] as $field) {
                $value = trim((string) $anchor->{$field});

                if ($value === '') {
                    continue;
                }

                $resolved = $this->findAnchorSubjects($value)->first();

                if ($resolved) {
                    $subjects->push($resolved);
                } else {
                    $notes[] = "{$anchor->subject_code}'s listed {$field} is \"{$value}\" — not found as its own subject in the system, shown as entered.";
                }
            }

            // Reverse: which OTHER subjects list this one as their
            // prerequisite/corequisite? ("what depends on COMP 003?")
            $dependents = Subject::query()
                ->where('id', '!=', $anchor->id)
                ->where(fn (Builder $q) => $q
                    ->where('prerequisite', 'like', "%{$anchor->subject_code}%")
                    ->orWhere('corequisite', 'like', "%{$anchor->subject_code}%"))
                ->with('program')
                ->get();

            $subjects = $subjects->concat($dependents);
        }

        return [
            'subjects' => $subjects->unique('id')->map(fn (Subject $s) => $this->formatSubjectModel($s, 'prerequisite'))->values()->all(),
            'notes' => $notes,
        ];
    }

    // ------------------------------------------------------------------
    // I. Faculty/uploader queries
    // ------------------------------------------------------------------

    private function retrieveFacultyUploader(string $message, array $history = [], string $role = 'faculty'): array
    {
        // "How many recent uploads?" — a broad question, not about one
        // subject. Guarded the same way as retrieveStats() (see its
        // docblock for the full "coincidental anchor" bug story) — a
        // message with no subject-code-shaped token in it never
        // legitimately anchors, no matter what findAnchorSubjects()'s
        // fallback layers might turn up.
        $namesNoSubject = !preg_match('/\b[A-Za-z]{2,6}\s?-?\s?\d{2,4}\b/', $message);
        $broadUploadQuestion = $namesNoSubject && (bool) preg_match('/\brecent\b|\blatest\b|\bilan\b|\bhow many\b/i', $message);

        $anchors = $broadUploadQuestion ? collect() : $this->findAnchorSubjects($message, $history);
        $notes = [];

        if ($anchors->isNotEmpty()) {
            foreach ($anchors as $anchor) {
                $files = Syllabus::where('subject_id', $anchor->id)->with('uploader')->get();

                if ($files->isEmpty()) {
                    $notes[] = "{$anchor->subject_code} has no uploaded syllabus, so there is no uploader to report.";

                    continue;
                }

                foreach ($files as $file) {
                    $uploader = $file->uploader?->name ?? 'an unknown user';
                    $notes[] = "{$anchor->subject_code}'s " . strtoupper($file->file_type) . " syllabus was uploaded by {$uploader} on " . $file->created_at->format('M j, Y') . '.';
                }
            }

            return [
                'subjects' => $anchors->map(fn (Subject $s) => $this->formatSubjectModel($s, 'faculty_uploader'))->all(),
                'notes' => $notes,
            ];
        }

        // Per-subject uploader lookup (the anchored branch above) stays
        // open to everyone — browsing a subject and its syllabus files
        // is already public to every authenticated role (CLAUDE.md §7).
        // These two below are system-WIDE activity views — who's
        // uploaded the most, everything uploaded recently across the
        // whole curriculum — which the admin/intern dashboard already
        // keeps admin/intern-only (faculty's own dashboard only shows
        // subjects THEY created), so Sage draws the same line rather
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

            return ['subjects' => [], 'notes' => $notes];
        }

        // "this month" / "recently" / "how many recent uploads" —
        // recent uploads generally. Leads with the actual total so a
        // "how many" question gets a real number, not just a list.
        $totalUploads = Syllabus::count();
        $notes[] = "There are {$totalUploads} syllabus files uploaded in total.";

        $recent = Syllabus::query()->with(['subject', 'uploader'])->orderByDesc('created_at')->limit(10)->get();

        foreach ($recent as $file) {
            if (!$file->subject) {
                continue;
            }

            $uploader = $file->uploader?->name ?? 'an unknown user';
            $notes[] = "{$file->subject->subject_code} — " . strtoupper($file->file_type) . " uploaded by {$uploader} on " . $file->created_at->format('M j, Y') . '.';
        }

        return ['subjects' => [], 'notes' => $notes];
    }

    // ------------------------------------------------------------------
    // B. Syllabus availability
    // ------------------------------------------------------------------

    private function retrieveSyllabusAvailability(string $message, array $history = []): array
    {
        // "Can you give me all the subjects that are in Curriculum
        // 2022-2023?" — added 2026-08-13 (Rico): curriculum_year is a
        // real column on syllabi, but nothing queried it before this,
        // so this genuinely had no answer anywhere — not a bug in the
        // sense of a wrong result, a real missing capability. Checked
        // first, before the single-subject anchor logic below, since
        // "which subjects are under year X" is inherently a filtered
        // list, never about one specific subject.
        if (preg_match('/curriculum|school\s?year|academic\s?year|\bAY\b/i', $message)
            && ($curriculumYear = $this->extractCurriculumYear($message)) !== null) {
            $subjects = Subject::with('program')
                ->whereHas('syllabi', fn (Builder $q) => $q->where('curriculum_year', $curriculumYear))
                ->get();

            // A note explicitly ties the returned subject(s) to the
            // curriculum year asked about — added 2026-08-13 after a
            // real flakiness bug: formatContextLine() (ChatbotService)
            // never mentions curriculum_year at all, only subject_code/
            // title/hours/etc, so Gemini had no textual confirmation
            // that the subject(s) it was given actually matched what was
            // asked. Same LIVE query, same result, sometimes answered
            // correctly and sometimes fell back to "wala akong nakita"
            // — the exact same non-determinism already fixed once for
            // an omitted "prerequisite: none" field (see
            // formatContextLine's docblock); this is that same class of
            // bug in a different spot, fixed the same way: state it
            // explicitly instead of leaving it implied.
            $notes = $subjects->isEmpty()
                ? ["No subjects have a syllabus filed under curriculum year {$curriculumYear}."]
                : ['The following subjects have a syllabus filed under curriculum year ' . $curriculumYear . ': '
                    . $subjects->pluck('subject_code')->implode(', ') . '.'];

            return [
                'subjects' => $subjects->map(fn (Subject $s) => $this->formatSubjectModel($s, 'syllabus_availability'))->all(),
                'notes' => $notes,
            ];
        }

        // "Meron bang syllabus ang COMP 016?" — asking about ONE
        // specific subject's availability, not a filtered list. Without
        // this check, a real bug (2026-08-12): the message doesn't
        // match any of the "wala pang"/"ilan"/"pinaka-complete" phrases
        // below, so it fell to the default "list subjects WITH a
        // syllabus" branch — which, if COMP 016 has none, silently
        // excludes the very subject being asked about, leaving Gemini
        // with an empty context and no way to answer "does IT have one".
        // "doesn't have"/"does not have"/"without a syllabus" added
        // 2026-08-13 — English equivalents of "wala pang"/"kulang" that
        // simply weren't recognized: "TELL ME ALL SUBJECTS that doesn't
        // have syllabus yet" fell through every branch below (this
        // filterPhrase gate, then the "missing" branch itself) and
        // landed on the DEFAULT "subjects WITH a syllabus" listing —
        // the exact opposite of what was asked.
        $filterPhrase = '/wala pang|kulang|missing|doesn\'?t have|does ?n\'?t have|don\'?t have|without (a |an )?syllabus|no syllabus|\bilan\b|\bhow many\b|\btotal\b|percentage|percent|pinaka-?complete|most complete/i';

        if (!preg_match($filterPhrase, $message)) {
            $anchors = $this->findAnchorSubjects($message, $history);

            if ($anchors->isNotEmpty()) {
                return [
                    'subjects' => $anchors->map(fn (Subject $s) => $this->formatSubjectModel($s, 'syllabus_availability'))->all(),
                    'notes' => [],
                ];
            }
        }

        $scope = $this->parseScope($message);

        if (preg_match('/pinaka-?complete|most complete/i', $message)) {
            $byYear = Subject::query()
                ->selectRaw('year_level, COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN EXISTS (SELECT 1 FROM syllabi WHERE syllabi.subject_id = subjects.id AND syllabi.deleted_at IS NULL) THEN 1 ELSE 0 END) as with_syllabus')
                ->groupBy('year_level')
                ->orderByDesc('with_syllabus')
                ->get();

            $notes = $byYear->map(fn ($row) => "Year {$row->year_level}: {$row->with_syllabus}/{$row->total} subjects have a syllabus.")->all();

            return ['subjects' => [], 'notes' => $notes];
        }

        $base = Subject::query()->with('program');
        $this->applyScope($base, $scope);

        if (preg_match('/wala pang|kulang|missing|doesn\'?t have|does ?n\'?t have|don\'?t have|without (a |an )?syllabus|no syllabus/i', $message)) {
            $subjects = (clone $base)->whereDoesntHave('syllabi')->get();

            return [
                'subjects' => $subjects->map(fn (Subject $s) => $this->formatSubjectModel($s, 'syllabus_availability'))->all(),
                'notes' => [],
            ];
        }

        if (preg_match('/ilan|how many|total|percentage|percent/i', $message)) {
            $total = (clone $base)->count();
            $withSyllabus = (clone $base)->whereHas('syllabi')->count();
            $pct = $total > 0 ? round($withSyllabus / $total * 100, 1) : 0;

            return [
                'subjects' => [],
                'notes' => ["{$withSyllabus} out of {$total} subjects in scope have an uploaded syllabus ({$pct}%)."],
            ];
        }

        // Default: "may syllabus na" / "list lahat ng available" —
        // subjects that DO have a syllabus.
        $subjects = (clone $base)->whereHas('syllabi')->get();

        return [
            'subjects' => $subjects->map(fn (Subject $s) => $this->formatSubjectModel($s, 'syllabus_availability'))->all(),
            'notes' => [],
        ];
    }

    // ------------------------------------------------------------------
    // D. Year level & semester queries
    // ------------------------------------------------------------------

    private function retrieveYearSemester(string $message, array $history = []): array
    {
        $scope = $this->parseScope($message);

        // "Can you tell me the subjects in year 2024?" — real bug
        // (2026-08-13): this curriculum's "year level" means 1st–4th
        // year of study, never a calendar year, but parseScope() only
        // recognizes ordinal forms (1st/2nd/3rd/4th/first/second/...) so
        // a bare "2024" silently resolves to no scope at all — and used
        // to then either wrongly anchor on an unrelated subject (fixed
        // separately in findAnchorSubjects(), see its docblock) or just
        // dump the entire unfiltered 16-subject catalog with nothing
        // explaining why "2024" didn't actually filter anything. Neither
        // is what was asked. Caught here before either of those paths
        // even runs: a calendar-year-shaped number with no valid
        // ordinal-year scope found alongside it means the question
        // itself doesn't map onto this curriculum's concept of "year" —
        // say so plainly instead of guessing.
        if (!$scope['year'] && !$scope['semester'] && preg_match('/\b(19|20)\d{2}\b/', $message, $calendarYear)) {
            return [
                'subjects' => [],
                'notes' => ["\"{$calendarYear[0]}\" is not a valid year level in this curriculum — year level here means 1st through 4th year of study (how far along in the program a subject is taken), not a calendar year. There is no subject data organized by calendar year."],
            ];
        }

        // "anong year level ang COMP 018?" / "anong semester ang Web
        // Development?" — asking about ONE subject's placement, not a
        // filtered list, and no explicit year/sem filter was given.
        // !$scope['program'] added 2026-08-13 — real bug: "What about
        // the subjects in DIT program?" explicitly names a whole
        // PROGRAM (a scope, not one subject), but the anchor lookup
        // still ran anyway and coincidentally FULLTEXT-matched "program"
        // as a prefix of "Programming" in three unrelated BSIT subjects
        // — then returned exactly THOSE three, ignoring the DIT scope
        // entirely. Naming a whole program is exactly as strong a
        // "this is a group/scope question" signal as naming a year or
        // semester already was, so it's guarded the same way.
        $anchors = $this->findAnchorSubjects($message, $history);

        if ($anchors->isNotEmpty() && !$scope['year'] && !$scope['semester'] && !$scope['program']) {
            return [
                'subjects' => $anchors->map(fn (Subject $s) => $this->formatSubjectModel($s, 'year_semester'))->all(),
                'notes' => [],
            ];
        }

        $query = Subject::query()->with('program');
        $this->applyScope($query, $scope);
        $subjects = $query->orderBy('year_level')->orderBy('semester')->get();

        $notes = [];
        if (preg_match('/ilan|how many/i', $message)) {
            $notes[] = 'Total subjects matching this year/semester scope: ' . $subjects->count() . '.';
        }

        return [
            'subjects' => $subjects->map(fn (Subject $s) => $this->formatSubjectModel($s, 'year_semester'))->all(),
            'notes' => $notes,
        ];
    }

    // ------------------------------------------------------------------
    // E. Units & hours
    // ------------------------------------------------------------------

    private function retrieveStats(string $message, array $history = [], string $role = 'faculty'): array
    {
        // Admin/system-meta counts — NOT about subjects at all, so these
        // are checked first and return immediately. Real gap (Rico,
        // 2026-08-13): "How many faculty accounts are existing?" and "I
        // mean, how many subject change request?" both classify as STATS
        // (they say "how many"), but retrieveStats() had no idea what to
        // do with them and fell through to the generic subject-count
        // aggregate below — answering with a curriculum subject count
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
        // this is enforced here in PHP, not by asking Gemini nicely not
        // to repeat it, so there's no prompt-engineering trick that gets
        // it to leak the real figure to a Faculty account.
        if (preg_match('/faculty (account|user)s?|mga faculty account/i', $message)) {
            if (!in_array($role, self::PRIVILEGED_ROLES, true)) {
                return $this->restrictedResponse('faculty account details');
            }

            $count = User::where('role', 'faculty')->count();

            return ['subjects' => [], 'notes' => ["There are {$count} faculty accounts in the system."]];
        }

        if (preg_match('/change request|edit request|request(s)? to edit|pending (na )?request/i', $message)) {
            if (!in_array($role, self::PRIVILEGED_ROLES, true)) {
                return $this->restrictedResponse('subject change request details');
            }

            $pendingOnly = (bool) preg_match('/\bpending\b/i', $message);

            $query = SubjectChangeRequest::query();
            $total = (clone $query)->count();
            $pending = (clone $query)->where('status', 'pending')->count();

            $notes = $pendingOnly
                ? ["There are {$pending} pending subject change requests awaiting review."]
                : ["There are {$total} subject change requests in total ({$pending} pending, " . ($total - $pending) . ' already reviewed).'];

            return ['subjects' => [], 'notes' => $notes];
        }

        // "Ilang units ang COMP 016?" — a specific subject's own
        // numbers, not an aggregate. Only treat this as a curriculum-
        // wide aggregate when no subject is actually being named.
        //
        // "Generic aggregate" phrasing skips the anchor lookup entirely
        // rather than trusting whatever findAnchorSubjects() returns —
        // real bug (2026-08-13, two different cases): "How many subjects
        // are existing sa system?" has no named subject at all, but its
        // keyword fallback (see textSearch()) FULLTEXT-matched the
        // standalone word "system" as a prefix of "Systems" in three
        // unrelated titles — a coincidental collision. Separately, "If
        // so, how many subjects are in BSIT?" has no code/title of its
        // own either, but historyFallback() picked up a subject code
        // mentioned several turns earlier in the SAME conversation and
        // anchored to THAT instead. Both are the same underlying mistake
        // — treating any collection-counting question ("how many/ilan
        // ...subject(s)...") as if it named one specific subject — so
        // both are guarded the same way: skip anchoring entirely
        // whenever the message is asking to count "subject(s)" as a
        // group, regardless of which fallback layer would have produced
        // the false anchor.
        // "number of" and "existed" added 2026-08-13 (second round) —
        // same drift risk flagged elsewhere: this guard's own trigger
        // words have to stay in sync with ChatbotQueryClassifier's STATS
        // trigger, or a message that gets correctly classified as STATS
        // can still slip past THIS guard and anchor wrongly anyway. "the
        // number of subjects existed in the system" is exactly that
        // case — classifies as STATS fine, but neither "ilan"/"how many"
        // nor "existing" (only "existed") were recognized here, so it
        // still tried to anchor and answered with a coincidental
        // FULLTEXT-prefix match on "system" (4 subjects) instead of the
        // true total (16).
        $countsSubjectsAsGroup = preg_match('/\bsubjects?\b/i', $message)
            && preg_match('/\bilan\b|\bhow many\b|\bnumber of\b/i', $message);

        $genericAggregate = $countsSubjectsAsGroup
            || (bool) preg_match('/\btotal\b|\bexist(ing|ed)\b|\blahat\b|\bbuong\b|\bkabuuan\b|\boverall\b/i', $message);

        $anchors = $genericAggregate ? collect() : $this->findAnchorSubjects($message, $history);

        if ($anchors->isNotEmpty()) {
            return [
                'subjects' => $anchors->map(fn (Subject $s) => $this->formatSubjectModel($s, 'stats'))->all(),
                'notes' => [],
            ];
        }

        $scope = $this->parseScope($message);
        $query = Subject::query()->with('program');
        $this->applyScope($query, $scope);
        $subjects = $query->get();

        $notes = [
            'Subject count in scope: ' . $subjects->count() . '.',
            'Total credited units in scope: ' . $subjects->sum('credited_units') . '.',
            'Total lecture hours in scope: ' . $subjects->sum('lecture_hours') . '.',
            'Total lab hours in scope: ' . $subjects->sum('lab_hours') . '.',
            // Added 2026-08-13 going through the curriculum spreadsheets'
            // actual columns (Rico) — tuition_hours is a real, tracked
            // field (already surfaced per-subject in formatContextLine),
            // but the aggregate totals here never summed it, so "total
            // tuition hours" questions had no aggregate answer at all.
            'Total tuition hours in scope: ' . $subjects->sum('tuition_hours') . '.',
        ];

        if (preg_match('/pinakamataas|highest/i', $message)) {
            $top = $subjects->sortByDesc('credited_units')->first();

            if ($top) {
                $notes[] = "Highest credited-units subject in scope: {$top->subject_code} — {$top->title} ({$top->credited_units} units).";
            }
        }

        if (preg_match('/pinakamabigat|heaviest/i', $message)) {
            $bySemesterUnits = $subjects->groupBy(fn (Subject $s) => "Year {$s->year_level} {$s->semester} sem")
                ->map(fn (Collection $group) => (float) $group->sum('credited_units'))
                ->sortDesc();

            if ($bySemesterUnits->isNotEmpty()) {
                $notes[] = 'Total credited units per year/semester (heaviest first): '
                    . $bySemesterUnits->map(fn ($units, $label) => "{$label}: {$units}")->implode(', ') . '.';
            }
        }

        // Also hand over the individual subjects in scope, not just the
        // aggregate notes above — a curriculum-sized list is cheap to
        // include, and some "stats" questions are really judgment calls
        // over titles ("Ilan ang programming-related subjects?") that
        // Gemini can only make if it can see them, not just a total.
        return [
            'subjects' => $subjects->map(fn (Subject $s) => $this->formatSubjectModel($s, 'stats'))->all(),
            'notes' => $notes,
        ];
    }

    // ------------------------------------------------------------------
    // F. Program comparison
    // ------------------------------------------------------------------

    private function retrieveProgramComparison(): array
    {
        $programs = Program::with('subjects')->get();
        $notes = [];

        foreach ($programs as $program) {
            $subjects = $program->subjects;
            $notes[] = "{$program->code} ({$program->name}): {$subjects->count()} subjects, "
                . $subjects->sum('credited_units') . ' total credited units, '
                . $subjects->pluck('year_level')->filter()->unique()->count() . ' year levels represented.';
        }

        // Title-based set difference — subject codes differ by program
        // prefix (COMP vs DIT) by design, so code comparison would be
        // meaningless; title is what actually answers "which subjects
        // are in one program but not the other".
        $bsit = $programs->firstWhere('code', 'BSIT');
        $dit = $programs->firstWhere('code', 'DIT');

        if ($bsit && $dit) {
            $bsitTitles = $bsit->subjects->pluck('title')->map(fn (string $t) => mb_strtolower($t));
            $ditTitles = $dit->subjects->pluck('title')->map(fn (string $t) => mb_strtolower($t));

            $onlyBsit = $bsit->subjects->reject(fn (Subject $s) => $ditTitles->contains(mb_strtolower($s->title)));
            $onlyDit = $dit->subjects->reject(fn (Subject $s) => $bsitTitles->contains(mb_strtolower($s->title)));
            $shared = $bsit->subjects->filter(fn (Subject $s) => $ditTitles->contains(mb_strtolower($s->title)));

            if ($onlyBsit->isNotEmpty()) {
                $notes[] = 'Subjects only in BSIT: ' . $onlyBsit->pluck('title')->implode(', ') . '.';
            }
            if ($onlyDit->isNotEmpty()) {
                $notes[] = 'Subjects only in DIT: ' . $onlyDit->pluck('title')->implode(', ') . '.';
            }
            if ($shared->isNotEmpty()) {
                $notes[] = 'Subjects offered in both programs (matched by title): ' . $shared->pluck('title')->implode(', ') . '.';
            }
        }

        return ['subjects' => [], 'notes' => $notes];
    }

    // ------------------------------------------------------------------
    // G. Subject type/category queries
    // ------------------------------------------------------------------

    private function retrieveSubjectCategory(string $message): array
    {
        $lower = mb_strtolower($message);
        $query = Subject::query()->with('program');
        $matchedPrefix = null;

        if (str_contains($lower, 'geed')) {
            $query->where('subject_code', 'like', 'GEED%');
        } elseif (str_contains($lower, 'nstp')) {
            $query->where(fn (Builder $q) => $q->where('subject_code', 'like', 'NSTP%')->orWhere('title', 'like', '%NSTP%'));
        } elseif (str_contains($lower, 'pathfit')) {
            $query->where(fn (Builder $q) => $q->where('subject_code', 'like', 'PATHFIT%')->orWhere('title', 'like', '%PATHFIT%'));
        } elseif (str_contains($lower, 'elective')) {
            $query->where('title', 'like', '%elective%');
        } elseif (preg_match('/purely lecture|walang lab/i', $message)) {
            $query->where(fn (Builder $q) => $q->where('lab_hours', 0)->orWhereNull('lab_hours'))->where('lecture_hours', '>', 0);
        } elseif (str_contains($lower, 'may lab') || str_contains($lower, 'laboratory component')) {
            $query->where('lab_hours', '>', 0);
        } elseif (str_contains($lower, 'accounting')) {
            $query->where('title', 'like', '%accounting%');
        } elseif ($matchedPrefix = $this->findMentionedCodePrefix($message)) {
            // "Can you tell me all the subjects that starts with the
            // course code 'COMP'?" / "ALL COMP subject code?" — real
            // bug (Rico, 2026-08-13): no capability anywhere queried by
            // prefix generically (only the three hardcoded GEED/NSTP/
            // PATHFIT ones above did), so this fell all the way to
            // GENERAL_SEARCH, whose keywordFallback() deliberately SKIPS
            // shared prefixes like "comp" (see its own docblock — a
            // real, different bug fixed 2026-08-12 by excluding them),
            // leaving nothing behind except a stale historyFallback()
            // match on whatever subject had dominated recent
            // conversation — one wrong subject instead of all thirteen.
            // Computed from the actual data, not hardcoded, so it stays
            // correct as the curriculum grows.
            $query->where('subject_code', 'like', "{$matchedPrefix}%");
        }

        $subjects = $query->get();

        return [
            'subjects' => $subjects->map(fn (Subject $s) => $this->formatSubjectModel($s, 'subject_category'))->all(),
            'notes' => [],
        ];
    }

    /**
     * Any REAL subject_code prefix (COMP, DIT, GEED, ...) mentioned in
     * the message as its own word — used to answer "all X subjects" /
     * "X subject code" / "starts with X" generically, for whichever
     * prefix is actually being asked about, not just the three special-
     * cased category keywords above.
     */
    private function findMentionedCodePrefix(string $message): ?string
    {
        $prefixes = Subject::query()
            ->pluck('subject_code')
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
     * try anchored to whatever subject was named in recent turns — see
     * historyFallback().
     *
     * @return array<int, array<string, mixed>>
     */
    public function textSearch(string $message, array $history = []): array
    {
        $aliased = $this->resolveAliasedSubject($message);

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
        // asking about a GROUP of subjects, which "all"/"lahat"/
        // "overall" is an unambiguous signal of. Real bug (2026-08-13):
        // "Can you tell me all the subjects that starts with the course
        // code 'COMP'?" kept re-anchoring on whichever ONE subject had
        // dominated recent conversation instead of correctly finding
        // none (this specific case is now its own real capability — see
        // ChatbotRetrievalService::findMentionedCodePrefix() — but the
        // underlying historyFallback overreach is general, not unique
        // to that one phrasing, so it's guarded here too). "overall"
        // added after "What about overall 1st subjects?" slipped past
        // the original "all"/"lahat"-only guard — \ball\b requires a
        // WORD boundary, which "overall" doesn't have around its "all".
        if (preg_match('/\ball\b|\blahat\b|\boverall\b/i', $message)) {
            return [];
        }

        return $this->historyFallback($history);
    }

    /**
     * Common IT-curriculum shorthand faculty actually type, mapped to
     * the ONE canonical subject title it should resolve to — not just
     * "improves" the match, but pins it to exactly one subject. Without
     * this (Rico, 2026-08-13): "OOP" matched nothing at all — no subject
     * title has a word literally starting with "oop" for the FULLTEXT
     * prefix search to catch — and "Web Dev" matched BOTH "Web
     * Development" (COMP 016) AND "Advanced Web and Mobile Development"
     * (DIT 103), since FULLTEXT boolean mode requires each word present
     * ANYWHERE in the title, not adjacently — both titles contain both
     * "Web" and "Development" as separate words. Textually expanding
     * "web dev" into "Web Development" and re-running it through that
     * same AND search doesn't fix that (tried first, still matched both)
     * — so this looks the subject up directly by its exact title
     * instead, which is unambiguous by construction.
     *
     * Deliberately small and curated, not an exhaustive dictionary — add
     * more here as real gaps turn up, same spirit as this file's
     * STOPWORDS list. Word-boundary matched so "oop" only fires on the
     * standalone token, never a substring inside an unrelated word.
     */
    private const SUBJECT_ALIASES = [
        'oop' => 'Object Oriented Programming',
        'web dev' => 'Web Development',
        'dsa' => 'Data Structures and Algorithms',
        'dbms' => 'Database Management Systems',
    ];

    private function resolveAliasedSubject(string $message): ?array
    {
        foreach (self::SUBJECT_ALIASES as $alias => $canonicalTitle) {
            if (!preg_match('/\b' . preg_quote($alias, '/') . '\b/i', $message)) {
                continue;
            }

            $subject = Subject::with('program')->where('title', $canonicalTitle)->first();

            if ($subject) {
                return $this->formatSubjectModel($subject, 'alias');
            }
        }

        return null;
    }

    /**
     * Resolves a text fragment (a subject code, or a title-ish phrase)
     * to actual Subject models — used by the structured strategies above
     * to find the "anchor" subject a question is really about.
     *
     * Falls back to $history when $text alone resolves to nothing — a
     * pronoun follow-up ("Ilan ang credit units NITO?") has no
     * identifying detail of its own, so every structured strategy that
     * needs an anchor (prerequisite, faculty_uploader, year_semester,
     * stats) gets the same "look at what was just discussed" recovery
     * general_search/subject_lookup/syllabus_content already had via
     * textSearch()'s own historyFallback() call — this just extends it
     * to also cover a message whose FIRST textSearch() attempt (with no
     * history involved yet) truly found nothing.
     */
    private function findAnchorSubjects(string $text, array $history = []): Collection
    {
        // SYLLABUS CONTENT matches are NEVER trusted as an anchor here,
        // full stop — not just downgraded when a stronger match also
        // exists (tried that first, 2026-08-13; still broke). Every
        // caller of findAnchorSubjects() (prerequisite, faculty_uploader,
        // syllabus_availability, year_semester, stats) is asking "did
        // the user name ONE specific subject", and a match that only
        // comes from an unrelated uploaded file's extracted TEXT
        // happening to contain a matching word is never a real answer to
        // that — it's always a coincidence, since nobody names a subject
        // by quoting a phrase from inside its syllabus. (SYLLABUS_CONTENT
        // itself is unaffected — that type never calls this method, it
        // reads textSearch() directly, where content matches are exactly
        // the point.) Second real bug of this exact shape (2026-08-13):
        // "Can you tell me the subjects in year 2024?" anchored on COMP
        // 001 purely because its uploaded file's text happened to
        // contain "2024" somewhere — the only match found at all, so the
        // earlier "only when competing with a stronger match" guard
        // never even triggered.
        $results = collect($this->textSearch($text))
            ->reject(fn (array $r) => $r['match_type'] === 'syllabus_content');

        $ids = $results->pluck('subject_id');

        // Same "all"/"lahat"/"overall" guard as textSearch()'s own
        // historyFallback call just below it — this is a SEPARATE
        // fallback path (this method calls textSearch($text) with no
        // $history above, so that guard alone doesn't cover this one).
        if ($ids->isEmpty() && !empty($history) && !preg_match('/\ball\b|\blahat\b|\boverall\b/i', $text)) {
            $ids = collect($this->historyFallback($history))->pluck('subject_id');
        }

        if ($ids->isEmpty()) {
            return collect();
        }

        return Subject::whereIn('id', $ids)->with('program')->get()
            ->sortBy(fn (Subject $s) => $ids->search($s->id))
            ->values();
    }

    /**
     * Per-keyword retry, FULLTEXT-only (no fuzzy). One bug this already
     * burned us on (2026-08-12): a generic conversational word ("credit",
     * "ilan", "send") let through the PHP fuzzy fallback can
     * coincidentally clear the similarity threshold against an unrelated
     * subject — worse than finding nothing. Also skips known shared code
     * prefixes (see sharedCodePrefixes()) — splitting "COMP 001" into
     * "comp" and "001" and searching "comp" ALONE matches nearly the
     * entire BSIT catalog, since it's a complete word shared by every
     * BSIT subject's code.
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
                $id = $result['subject_id'];

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
     * The subject being discussed is almost always still named by code
     * somewhere in the last few turns — our own replies always mention
     * it — so re-scanning recent history for something code-shaped and
     * searching THAT re-anchors a pronoun follow-up to the right subject
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

        foreach ($this->extractSubjectCodeLikeTokens($recentText) as $code) {
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
     * Alpha prefixes (e.g. "comp", "dit") shared by 2+ subject codes —
     * see keywordFallback()'s docblock for why these must never be
     * searched alone. Computed from the actual data rather than
     * hardcoded, so it stays correct if a new program/prefix is added.
     *
     * @return string[]
     */
    private function sharedCodePrefixes(): array
    {
        return Subject::query()
            ->pluck('subject_code')
            ->map(fn (string $code) => strtolower(trim(preg_replace('/[^A-Za-z].*$/', '', $code))))
            ->filter(fn (string $prefix) => $prefix !== '')
            ->countBy()
            ->filter(fn (int $count) => $count > 1)
            ->keys()
            ->all();
    }

    /**
     * Subject codes in this curriculum are a short letter prefix plus a
     * number, with or without a space ("COMP 001", "COMP001", "DIT 101").
     *
     * @return string[]
     */
    private function extractSubjectCodeLikeTokens(string $text): array
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
     * something PRIVILEGED_ROLES-only. `subjects`/`notes` stay empty on
     * purpose — the real data (the actual count, the actual names) is
     * never computed for this role in the first place, so there is
     * nothing for this note, or Gemini, to leak even by accident. The
     * marker prefix is a private wire format between here and
     * ChatbotService::formatContext() — see its handling of it — not
     * something meant to reach the user verbatim.
     *
     * @param  string  $what  a bare noun phrase (e.g. "faculty account
     *                        details") — ChatbotService::accessRestrictedNote()
     *                        wraps it into a full sentence, so this must
     *                        NOT itself end in "is restricted to..." or
     *                        any other trailing clause.
     * @return array{subjects: array<int, array<string, mixed>>, notes: string[]}
     */
    private function restrictedResponse(string $what): array
    {
        return ['subjects' => [], 'notes' => ["ACCESS_RESTRICTED: {$what}"]];
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

        // "...(year|subjects?)" — not just "year", added 2026-08-13 after
        // "What about overall 1st subjects?" resolved to no scope at
        // all ("1st" alone, with no "year"/"sem" suffix, matched
        // nothing) and fell through to anchor/history guessing instead
        // of being read as "1st year subjects", which is clearly what
        // was meant given this app is never about anything BUT subjects.
        $year = match (true) {
            (bool) preg_match('/\b(1st|first)\s?(year|subjects?)\b/i', $message) => 1,
            (bool) preg_match('/\b(2nd|second)\s?(year|subjects?)\b/i', $message) => 2,
            (bool) preg_match('/\b(3rd|third)\s?(year|subjects?)\b/i', $message) => 3,
            (bool) preg_match('/\b(4th|fourth)\s?(year|subjects?)\b/i', $message) => 4,
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

    private function formatSubjectModel(Subject $subject, string $matchType): array
    {
        return [
            'subject_id' => $subject->id,
            'subject_code' => $subject->subject_code,
            'title' => $subject->title,
            'program' => $subject->program?->code,
            'year_level' => $subject->year_level,
            'semester' => $subject->semester,
            'prerequisite' => $subject->prerequisite,
            'corequisite' => $subject->corequisite,
            'lecture_hours' => $subject->lecture_hours,
            'lab_hours' => $subject->lab_hours,
            'credited_units' => $subject->credited_units,
            'tuition_hours' => $subject->tuition_hours,
            'match_type' => $matchType,
            'score' => 100.0,
        ];
    }
}
