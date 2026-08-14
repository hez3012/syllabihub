<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseChangeRequest;
use App\Models\Syllabus;
use App\Models\User;
use App\Services\ChatbotQueryClassifier as Type;
use App\Services\ChatbotRetrievalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Feature tests for each RAG retrieval strategy in
 * ChatbotRetrievalService — one representative case per
 * chatbot-test-cases.md category, verifying the actual DB query
 * returns the right data (not just that a query type gets picked —
 * see ChatbotQueryClassifierTest for that).
 *
 * DatabaseTransactions is safe here: every test either (a) reads
 * already-seeded/committed courses (CourseSeeder's COMP/DIT rows) by
 * their course_code/title — those columns are never touched, so
 * FULLTEXT sees them fine — or (b) UPDATEs a non-indexed column
 * (prerequisite/corequisite) on an already-committed row, or queries a
 * plain (non-FULLTEXT) WHERE/EXISTS, all of which see a transaction's
 * own uncommitted writes normally. Nothing here inserts a new row and
 * then expects FULLTEXT to find it by course_code/title within the
 * same test — that's the one case that would need SearchControllerTest's
 * commit+cleanup approach instead.
 */
class ChatbotRetrievalServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ChatbotRetrievalService $retrieval;

    protected function setUp(): void
    {
        parent::setUp();

        $this->retrieval = app(ChatbotRetrievalService::class);
    }

    // ------------------------------------------------------------------
    // C. Prerequisites
    // ------------------------------------------------------------------

    public function test_prerequisite_forward_lookup_resolves_the_listed_prereq_to_a_real_course(): void
    {
        Course::where('course_code', 'COMP 003')->update(['prerequisite' => 'COMP 002']);

        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'Ano ang prereq ng COMP 003?');

        $codes = collect($result['courses'])->pluck('course_code');
        $this->assertTrue($codes->contains('COMP 003'));
        $this->assertTrue($codes->contains('COMP 002'));
    }

    public function test_prerequisite_reverse_lookup_finds_dependents(): void
    {
        Course::where('course_code', 'COMP 003')->update(['prerequisite' => 'COMP 002']);

        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'Ano-anong courses ang naka-depende sa COMP 002?');

        $codes = collect($result['courses'])->pluck('course_code');
        $this->assertTrue($codes->contains('COMP 002'));
        $this->assertTrue($codes->contains('COMP 003'), 'COMP 003 lists COMP 002 as its prerequisite, so it should surface as a dependent.');
    }

    public function test_prerequisite_no_prereq_listed_excludes_courses_that_have_one(): void
    {
        Course::where('course_code', 'COMP 003')->update(['prerequisite' => 'COMP 002']);

        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'Ano-anong courses ang walang prerequisite?');

        $codes = collect($result['courses'])->pluck('course_code');
        $this->assertTrue($codes->contains('COMP 001'));
        $this->assertFalse($codes->contains('COMP 003'));
    }

    /**
     * Regression (Rico, 2026-08-13): the mirror image of the "walang
     * prerequisite" test above — that branch existed alone, this one
     * never did. Without it, "which courses HAVE a prerequisite" (no
     * code/title of its own) fell through to findAnchorCourses(), which
     * — with real conversation history present — kept re-anchoring on
     * whichever course had dominated recent turns (COMP 001, live) and
     * answering about THAT one course's own null prerequisite instead
     * of the aggregate question actually asked, repeatedly, across
     * follow-ups. History here is deliberately non-empty and repeatedly
     * mentions COMP 001, specifically to prove this branch is now
     * checked BEFORE the anchor/history-fallback path ever runs.
     */
    public function test_prerequisite_which_courses_have_one_ignores_dominant_history_course(): void
    {
        Course::where('course_code', 'COMP 003')->update(['prerequisite' => 'COMP 002']);

        $history = [
            ['role' => 'user', 'content' => 'Ano ang COMP 001?'],
            ['role' => 'assistant', 'content' => 'Ang COMP 001 — Introduction to Computing ay walang prerequisite (none).'],
            ['role' => 'user', 'content' => 'Meron pa bang iba?'],
            ['role' => 'assistant', 'content' => 'Para sa COMP 001, wala pa ring prerequisite na nakalagay.'],
        ];

        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'Ano-anong mga course na existing sa system na MAYROONG prerequisite course?', $history);

        $codes = collect($result['courses'])->pluck('course_code');
        $this->assertTrue($codes->contains('COMP 003'));
        $this->assertFalse($codes->contains('COMP 001'), 'COMP 001 has no prerequisite — it must not appear just because it dominated recent history.');
    }

    public function test_prerequisite_which_courses_have_one_reports_none_honestly_when_true(): void
    {
        // Default seed data: no course has a prerequisite set at all.
        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'Anong course ang mayroong lamang prerequisite course?');

        $this->assertEmpty($result['courses']);
        $this->assertStringContainsString('No courses', implode(' ', $result['notes']));
    }

    /** "Which course HAS THE MOST prerequisites" must NOT be caught by the existence-check branch above — it's a different, superlative question. */
    public function test_prerequisite_most_prerequisites_question_is_not_treated_as_existence_check(): void
    {
        Course::where('course_code', 'COMP 003')->update(['prerequisite' => 'COMP 002']);

        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'Which course has the most prerequisites?');

        // Falls through to the generic text-search fallback (no code/
        // title named) rather than the "list everything with a
        // prerequisite" aggregate — this assertion only guards against
        // it being misrouted into that other branch, not any specific
        // result shape.
        $this->assertIsArray($result['courses']);
    }

    /**
     * Regression (2026-08-13): co-requisite had the same two-sided gap
     * prerequisite did — found by going through the curriculum
     * spreadsheets' actual columns for what other "which courses HAVE/
     * HAVE NO X" questions this pattern applies to. Only per-course
     * co-requisite lookup existed before; "which courses have one at
     * all" was never its own aggregate query.
     */
    public function test_corequisite_which_courses_have_one(): void
    {
        Course::where('course_code', 'COMP 003')->update(['corequisite' => 'COMP 002']);

        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'Ano-anong courses ang mayroong co-requisite?');

        $codes = collect($result['courses'])->pluck('course_code');
        $this->assertTrue($codes->contains('COMP 003'));
        $this->assertFalse($codes->contains('COMP 001'));
    }

    public function test_corequisite_which_courses_have_none(): void
    {
        Course::where('course_code', 'COMP 003')->update(['corequisite' => 'COMP 002']);

        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'Ano-anong courses ang walang co-requisite?');

        $codes = collect($result['courses'])->pluck('course_code');
        $this->assertTrue($codes->contains('COMP 001'));
        $this->assertFalse($codes->contains('COMP 003'));
    }

    public function test_corequisite_which_courses_have_one_reports_none_honestly_when_true(): void
    {
        // Default seed data: no course has a co-requisite set at all.
        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'May co-requisite ba ang kahit anong course?');

        $this->assertEmpty($result['courses']);
        $this->assertStringContainsString('No courses', implode(' ', $result['notes']));
    }

    // ------------------------------------------------------------------
    // B. Syllabus availability
    // ------------------------------------------------------------------

    public function test_syllabus_availability_missing_excludes_courses_that_have_a_file(): void
    {
        $course = Course::where('course_code', 'COMP 016')->firstOrFail();
        $uploader = User::factory()->create(['role' => 'admin']);
        Syllabus::factory()->create(['course_id' => $course->id, 'file_type' => 'pdf', 'uploaded_by' => $uploader->id]);

        // A course known to have no syllabus AT THIS MOMENT, rather
        // than a hardcoded "COMP 001" — the shared dev DB can carry real
        // uploads from manual browser testing (Rico uploaded to COMP 001
        // himself, 2026-08-13), and a fixed assumption breaks the moment
        // someone actually uploads something to that specific course.
        $stillMissing = Course::whereDoesntHave('syllabi')->where('id', '!=', $course->id)->first();
        $this->assertNotNull($stillMissing, 'Need at least one other course with no syllabus for this assertion to mean anything.');

        $result = $this->retrieval->retrieve(Type::SYLLABUS_AVAILABILITY, 'Aling mga courses ang wala pang syllabus?');

        $codes = collect($result['courses'])->pluck('course_code');
        $this->assertFalse($codes->contains('COMP 016'), 'COMP 016 now has a syllabus, so it must not appear in the "missing" list.');
        $this->assertTrue($codes->contains($stillMissing->course_code));
    }

    /**
     * Regression (2026-08-13, live session): "TELL ME ALL SUBJECTS that
     * doesn't have syllabus yet" matched none of "wala pang"/"kulang"/
     * "missing" (all Tagalog except the one stray English word), so it
     * fell through to the DEFAULT branch — courses WITH a syllabus, the
     * exact opposite of what was asked.
     */
    public function test_syllabus_availability_english_does_not_have_phrasing(): void
    {
        $course = Course::where('course_code', 'COMP 016')->firstOrFail();
        $uploader = User::factory()->create(['role' => 'admin']);
        Syllabus::factory()->create(['course_id' => $course->id, 'file_type' => 'pdf', 'uploaded_by' => $uploader->id]);

        $result = $this->retrieval->retrieve(Type::SYLLABUS_AVAILABILITY, "Tell me all subjects that doesn't have syllabus yet.");

        $codes = collect($result['courses'])->pluck('course_code');
        $this->assertFalse($codes->contains('COMP 016'), 'COMP 016 now has a syllabus, so it must not appear in the "missing" list.');
        $this->assertGreaterThanOrEqual(14, $codes->count());
    }

    public function test_syllabus_availability_percentage_note_is_correct(): void
    {
        $course = Course::where('course_code', 'COMP 016')->firstOrFail();
        $uploader = User::factory()->create(['role' => 'admin']);

        // Baselines BEFORE this test's own addition — both the "with
        // syllabus" count AND the total course count are asserted as
        // deltas/live values rather than hardcoded absolutes, since the
        // shared dev DB can carry real courses/syllabi from manual
        // browser testing (this already happened once for syllabus
        // counts — see this file's own docblock; the total course count
        // needs the same treatment, since a manually-added test course
        // shifts it too).
        $withSyllabusBefore = Course::whereHas('syllabi')->count();
        $totalCourses = Course::count();

        Syllabus::factory()->create(['course_id' => $course->id, 'file_type' => 'pdf', 'uploaded_by' => $uploader->id]);

        $result = $this->retrieval->retrieve(Type::SYLLABUS_AVAILABILITY, 'Ilan na ang may syllabus?');

        $this->assertNotEmpty($result['notes']);
        $this->assertStringContainsString(($withSyllabusBefore + 1) . " out of {$totalCourses}", $result['notes'][0]);
    }

    /**
     * Regression (2026-08-13): curriculum_year is a real column on
     * syllabi, but nothing queried it before this — "Can you give me
     * all the courses that are in Curriculum 2022-2023?" had no
     * capability anywhere and always answered "wala akong nakita".
     */
    public function test_syllabus_availability_filters_by_curriculum_year(): void
    {
        $inScope = Course::where('course_code', 'COMP 016')->firstOrFail();
        $outOfScope = Course::where('course_code', 'COMP 020')->firstOrFail();
        $uploader = User::factory()->create(['role' => 'admin']);

        Syllabus::factory()->create(['course_id' => $inScope->id, 'file_type' => 'pdf', 'uploaded_by' => $uploader->id, 'curriculum_year' => '2022-2023']);
        Syllabus::factory()->create(['course_id' => $outOfScope->id, 'file_type' => 'pdf', 'uploaded_by' => $uploader->id, 'curriculum_year' => '2023-2024']);

        $result = $this->retrieval->retrieve(Type::SYLLABUS_AVAILABILITY, 'Can you give me all the courses that are in Curriculum 2022-2023?');

        $codes = collect($result['courses'])->pluck('course_code');
        $this->assertTrue($codes->contains('COMP 016'));
        $this->assertFalse($codes->contains('COMP 020'));

        // Regression (2026-08-13, second round): the SAME query above,
        // live, sometimes answered correctly and sometimes gave the
        // strict "wala akong nakita" refusal — same retrieved course
        // every time, but nothing in the context ever explicitly said
        // "this matches curriculum year 2022-2023", so Gemini had to
        // guess whether it did. A note stating it outright removes that
        // ambiguity instead of leaving it implied.
        $this->assertStringContainsString('2022-2023', implode(' ', $result['notes']));
        $this->assertStringContainsString('COMP 016', implode(' ', $result['notes']));
    }

    public function test_syllabus_availability_curriculum_year_with_no_matches_reports_zero_not_a_refusal(): void
    {
        $result = $this->retrieval->retrieve(Type::SYLLABUS_AVAILABILITY, 'Ano ang courses sa curriculum year 2099-2100?');

        $this->assertEmpty($result['courses']);
        $this->assertStringContainsString('2099-2100', $result['notes'][0]);
    }

    /**
     * Regression (2026-08-13): asking about ONE specific course by
     * code used to also pull in an unrelated course whenever that
     * OTHER course's uploaded file coincidentally contained the same
     * digits somewhere in its (unrelated) extracted text — a
     * coincidental syllabus_content match diluting a precise code
     * lookup that should only ever mean one thing.
     */
    public function test_year_semester_exact_code_ignores_coincidental_syllabus_content_match(): void
    {
        $named = Course::where('course_code', 'COMP 016')->firstOrFail();
        $unrelated = Course::where('course_code', 'COMP 001')->firstOrFail();
        $uploader = User::factory()->create(['role' => 'admin']);

        Syllabus::factory()->create([
            'course_id' => $unrelated->id,
            'file_type' => 'pdf',
            'uploaded_by' => $uploader->id,
            'raw_text' => 'This unrelated template happens to mention 016 somewhere in its text.',
        ]);

        $result = $this->retrieval->retrieve(Type::YEAR_SEMESTER, 'Anong year level ang COMP 016?');

        $this->assertCount(1, $result['courses']);
        $this->assertSame('COMP 016', $result['courses'][0]['course_code']);
    }

    /**
     * Regression (2026-08-13, live session): "Can you tell me the
     * courses in year 2024?" wrongly anchored on COMP 001 purely
     * because an unrelated uploaded file's extracted text happened to
     * contain "2024" somewhere — the only match found at all, so the
     * earlier "only when competing with a stronger match" guard (see
     * the test above) never even triggered. syllabus_content matches are
     * now excluded from findAnchorCourses() unconditionally, not just
     * when a stronger match coexists — see that method's own docblock.
     */
    public function test_year_semester_ignores_coincidental_content_match_even_as_the_only_result(): void
    {
        $unrelated = Course::where('course_code', 'COMP 001')->firstOrFail();
        $uploader = User::factory()->create(['role' => 'admin']);

        Syllabus::factory()->create([
            'course_id' => $unrelated->id,
            'file_type' => 'pdf',
            'uploaded_by' => $uploader->id,
            'raw_text' => 'This unrelated accomplishment report happens to mention the year 2024 somewhere in its text.',
        ]);

        $result = $this->retrieval->retrieve(Type::YEAR_SEMESTER, 'Can you tell me the courses in year 2024?');

        $codes = collect($result['courses'])->pluck('course_code');
        $this->assertFalse($codes->contains('COMP 001'), 'COMP 001 must not be singled out just because its uploaded file happens to mention "2024".');
    }

    /**
     * Regression (2026-08-13, live session): on top of the false-anchor
     * bug above, the fallback behavior itself was also unhelpful — it
     * silently dumped the entire unfiltered 16-course catalog with no
     * explanation, since "2024" doesn't match any of the ordinal
     * year-level forms parseScope() recognizes (1st/2nd/3rd/4th/...).
     * "Year level" in this curriculum means 1st-4th year of study, never
     * a calendar year — Sage should say that plainly instead of
     * guessing or dumping everything.
     */
    public function test_year_semester_calendar_year_gets_a_clarifying_note_not_the_whole_catalog(): void
    {
        $result = $this->retrieval->retrieve(Type::YEAR_SEMESTER, 'Can you tell me the courses in year 2024?');

        $this->assertEmpty($result['courses']);
        $this->assertStringContainsString('2024', implode(' ', $result['notes']));
        $this->assertStringContainsString('not a valid year level', implode(' ', $result['notes']));
    }

    // ------------------------------------------------------------------
    // D. Year level & semester
    // ------------------------------------------------------------------

    public function test_year_semester_filter_scoped_to_program(): void
    {
        $result = $this->retrieval->retrieve(Type::YEAR_SEMESTER, 'Ano-anong courses sa First Year, First Semester ng BSIT?');

        $codes = collect($result['courses'])->pluck('course_code');
        $this->assertTrue($codes->contains('COMP 001'));
        $this->assertTrue($codes->contains('COMP 002'));
        $this->assertFalse($codes->contains('DIT 101'), 'DIT 101 is also Year 1/1st sem but a different program — BSIT scope should exclude it.');
    }

    public function test_year_semester_single_course_placement(): void
    {
        $result = $this->retrieval->retrieve(Type::YEAR_SEMESTER, 'Anong year level ang COMP 016?');

        $this->assertCount(1, $result['courses']);
        $this->assertSame('COMP 016', $result['courses'][0]['course_code']);
        $this->assertSame(2, $result['courses'][0]['year_level']);
    }

    /**
     * Regression (2026-08-13, live session): "What about the courses
     * in DIT program?" has no count/year/comparison word, so it had no
     * classifier home before (now routed to YEAR_SEMESTER, which
     * already had correct program scoping — see ChatbotQueryClassifierTest
     * Z1). But even after that, the anchor-lookup here would still run
     * FIRST and coincidentally FULLTEXT-match "program" as a prefix of
     * "Programming" in three unrelated BSIT courses, ignoring the DIT
     * scope entirely — fixed by skipping the anchor branch whenever an
     * explicit program scope was found, the same way year/semester
     * scope already did.
     */
    public function test_year_semester_program_only_scope_ignores_coincidental_anchor_match(): void
    {
        $result = $this->retrieval->retrieve(Type::YEAR_SEMESTER, 'What about the courses in DIT program?');

        $codes = collect($result['courses'])->pluck('course_code');
        $this->assertEquals(['DIT 101', 'DIT 102', 'DIT 103'], $codes->sort()->values()->all());
    }

    /**
     * Regression (2026-08-13, live session): "1st" alone (no "year"/
     * "sem" suffix) resolved to no scope at all, so "What about overall
     * 1st courses?" fell through to anchor/history guessing instead of
     * being read as "1st year courses" — the only sensible reading in
     * an app that's never about anything but courses.
     */
    public function test_year_semester_bare_ordinal_without_year_suffix_still_scopes(): void
    {
        $result = $this->retrieval->retrieve(Type::YEAR_SEMESTER, 'What about overall 1st courses?');

        $codes = collect($result['courses'])->pluck('course_code');
        $this->assertTrue($codes->contains('COMP 001'));
        $this->assertTrue($codes->contains('DIT 101'), 'No program was named, so both BSIT and DIT Year 1 courses should be included.');
        $this->assertFalse($codes->contains('COMP 016'), 'COMP 016 is Year 2 — must not appear in a Year 1 scope.');
    }

    public function test_course_lookup_of_a_nonexistent_code_finds_nothing(): void
    {
        // A5 in chatbot-test-cases.md: "Mayroon bang course na COMP
        // 025?" style question about a plausible-looking but nonexistent
        // code should find nothing — not a fuzzy near-match to a real
        // one — so Groq's strict-grounding "not found" rule actually
        // applies instead of silently answering about the wrong course.
        $result = $this->retrieval->retrieve(Type::COURSE_LOOKUP, 'Mayroon bang course na ZZZ 999?');

        $this->assertEmpty($result['courses']);
    }

    /**
     * Regression (2026-08-13): "OOP" matched nothing at all — no course
     * title has a word literally starting with "oop" for the FULLTEXT
     * prefix search to catch, and it's too short for the fuzzy fallback
     * to clear the similarity threshold either. resolveAliases() maps it
     * straight to its one real course.
     */
    public function test_course_lookup_resolves_common_abbreviation_oop(): void
    {
        $result = $this->retrieval->retrieve(Type::GENERAL_SEARCH, 'OOP');

        $this->assertCount(1, $result['courses']);
        $this->assertSame('COMP 011', $result['courses'][0]['course_code']);
    }

    /**
     * Regression (2026-08-13): "Web Dev" technically matched something,
     * but BOTH "Web Development" (COMP 016) AND "Advanced Web and Mobile
     * Development" (DIT 103) — both titles share the word "Web". Rico's
     * expectation was that this shorthand means exactly one course.
     */
    public function test_course_lookup_resolves_common_abbreviation_web_dev(): void
    {
        $result = $this->retrieval->retrieve(Type::GENERAL_SEARCH, 'Web Dev');

        $this->assertCount(1, $result['courses']);
        $this->assertSame('COMP 016', $result['courses'][0]['course_code']);
    }

    // ------------------------------------------------------------------
    // E. Units & hours
    // ------------------------------------------------------------------

    public function test_stats_single_course_returns_its_own_numbers_not_an_aggregate(): void
    {
        $result = $this->retrieval->retrieve(Type::STATS, 'Ilang units ang COMP 016?');

        $this->assertCount(1, $result['courses']);
        $this->assertSame('COMP 016', $result['courses'][0]['course_code']);
        $this->assertEquals(3.0, $result['courses'][0]['credited_units']);
    }

    public function test_stats_aggregate_totals_bsit_curriculum(): void
    {
        // Computed live rather than hardcoded ("13 courses, 39 units") —
        // the shared dev DB can carry real courses from manual browser
        // testing (this already happened once for syllabus counts — see
        // this file's own docblock), so a fixed BSIT count/sum breaks the
        // moment someone adds a course through the UI. Querying the same
        // scope the retrieval itself uses keeps this a real regression
        // check (does the aggregate match reality) rather than a
        // snapshot of today's seed data.
        // ->get()->sum() (a Collection sum over loaded models), not a SQL
        // sum(), to match retrieveStats()'s own arithmetic exactly — a
        // DB-level SUM() on a DECIMAL column preserves scale (e.g.
        // "42.0"), but retrieveStats() sums an already-loaded Collection
        // in plain PHP, which prints "42" with no trailing zero.
        $bsitCourses = Course::whereHas('program', fn ($q) => $q->where('code', 'BSIT'))->get();
        $expectedCount = $bsitCourses->count();
        $expectedUnits = $bsitCourses->sum('credited_units');

        $result = $this->retrieval->retrieve(Type::STATS, 'Ilan ang total units ng buong BSIT curriculum?');

        $notesText = implode(' ', $result['notes']);
        $this->assertStringContainsString("Course count in scope: {$expectedCount}", $notesText);
        $this->assertStringContainsString("Total credited units in scope: {$expectedUnits}", $notesText);
    }

    /**
     * Regression (2026-08-13): tuition_hours is a real, tracked column
     * (already surfaced per-course), but the aggregate STATS totals
     * never summed it — found going through the curriculum spreadsheets'
     * actual columns for gaps of this shape.
     */
    public function test_stats_aggregate_totals_include_tuition_hours(): void
    {
        $result = $this->retrieval->retrieve(Type::STATS, 'Ilan ang total tuition hours ng buong BSIT curriculum?');

        $this->assertStringContainsString('Total tuition hours in scope:', implode(' ', $result['notes']));
    }

    /**
     * Proves the count is genuinely LIVE, not just "not hardcoded in the
     * test" — a course added mid-test (simulating a faculty/admin adding
     * one through the UI moments before someone asks Sage) is reflected
     * in the very next STATS answer, no caching/staleness anywhere in
     * between. Rico, 2026-08-13: explicit ask after the "yeah" test
     * course he added manually exposed hardcoded counts in these tests.
     */
    public function test_stats_generic_total_reflects_a_course_added_moments_ago(): void
    {
        $before = Course::count();

        Course::factory()->create();

        $result = $this->retrieval->retrieve(Type::STATS, 'How many courses are existing sa system?');

        $notesText = implode(' ', $result['notes']);
        $this->assertStringContainsString('Course count in scope: ' . ($before + 1), $notesText);
        $this->assertCount($before + 1, $result['courses']);
    }

    /** Same proof as above, the other direction: a just-deleted (soft-deleted) course drops out of the very next count. */
    public function test_stats_generic_total_reflects_a_course_deleted_moments_ago(): void
    {
        $target = Course::factory()->create();
        $before = Course::count();

        $target->delete();

        $result = $this->retrieval->retrieve(Type::STATS, 'How many courses are existing sa system?');

        $notesText = implode(' ', $result['notes']);
        $this->assertStringContainsString('Course count in scope: ' . ($before - 1), $notesText);
        $this->assertCount($before - 1, $result['courses']);
    }

    /**
     * Regression (2026-08-13): this message names no course at all, but
     * its keyword fallback (see textSearch()) used to FULLTEXT-match the
     * standalone word "system" as a prefix of "Systems" in three
     * unrelated titles (Database Management Systems, Systems Integration
     * and Architecture, Advanced Database Systems) — findAnchorCourses()
     * then treated that coincidence as the user naming those 3 courses,
     * so this answered "3" instead of the true curriculum-wide total.
     */
    public function test_stats_generic_total_ignores_coincidental_keyword_match(): void
    {
        // Computed live, not hardcoded — see test_stats_aggregate_totals_
        // bsit_curriculum's docblock above for why.
        $expectedTotal = Course::count();

        $result = $this->retrieval->retrieve(Type::STATS, 'How many courses are existing sa system?');

        $notesText = implode(' ', $result['notes']);
        $this->assertStringContainsString("Course count in scope: {$expectedTotal}", $notesText);
        $this->assertCount($expectedTotal, $result['courses']);
    }

    /**
     * Regression (2026-08-13, live session, fourth round): "the number
     * of courses existed in the system" used to anchor on a
     * coincidental FULLTEXT-prefix match ("system*" -> "Systems") and
     * answer "4" instead of the true total of 16 — the SAME bug as the
     * test above, just via "number of"/"existed" instead of "how many"/
     * "existing", which neither the classifier nor this guard
     * recognized as count language yet.
     */
    public function test_stats_number_of_courses_existed_ignores_coincidental_keyword_match(): void
    {
        // Computed live, not hardcoded — see test_stats_aggregate_totals_
        // bsit_curriculum's docblock above for why.
        $expectedTotal = Course::count();

        $result = $this->retrieval->retrieve(Type::STATS, 'can you tell me the number of courses existed in the system?');

        $notesText = implode(' ', $result['notes']);
        $this->assertStringContainsString("Course count in scope: {$expectedTotal}", $notesText);
        $this->assertCount($expectedTotal, $result['courses']);
    }

    /**
     * Regression (2026-08-13, live session): "If so, how many courses
     * are in BSIT?" has no code/title of its own, but historyFallback()
     * used to pick up "COMP 008" — mentioned several turns earlier in
     * the SAME conversation — and wrongly anchor to just that one
     * course instead of counting the whole BSIT program. History here
     * is a realistic slice of Rico's actual transcript, not a synthetic
     * minimal case, specifically to reproduce the coincidence.
     */
    public function test_stats_generic_program_total_ignores_stale_history_anchor(): void
    {
        // Computed live, not hardcoded — see test_stats_aggregate_totals_
        // bsit_curriculum's docblock above for why. (The "13"/"39" INSIDE
        // the fake $history below is deliberately left as flavor text —
        // it's simulating a stale number from an earlier turn, which is
        // exactly what this test proves gets ignored; it doesn't need to
        // match today's real BSIT count.)
        $expectedCount = Course::whereHas('program', fn ($q) => $q->where('code', 'BSIT'))->count();

        $history = [
            ['role' => 'user', 'content' => 'How many courses existing sa BSIT 2nd Year?'],
            ['role' => 'assistant', 'content' => "Mayroon kang 4 na courses para sa BSIT 2nd Year sa system:\n\n1. COMP 008 — Data Structures and Algorithms\n2. COMP 011 — Object Oriented Programming"],
            ['role' => 'user', 'content' => 'How many BSIT courses overall?'],
            ['role' => 'assistant', 'content' => 'Mayroong kabuuang 13 na BSIT courses sa system ngayon, na may total credited units na 39.'],
        ];

        $result = $this->retrieval->retrieve(Type::STATS, 'If so, how many courses are in BSIT?', $history);

        $notesText = implode(' ', $result['notes']);
        $this->assertStringContainsString("Course count in scope: {$expectedCount}", $notesText);
        $this->assertCount($expectedCount, $result['courses']);
    }

    /**
     * Proves this is a genuinely LIVE count, same as the course-count
     * proofs above (Rico, 2026-08-13) — two faculty accounts created
     * mid-test are reflected in the very next answer, no caching
     * anywhere. Delta-based (not a hardcoded absolute) for the same
     * reason: UserSeeder commits a faculty test account outside this
     * test's transaction, so this shouldn't depend on what else happens
     * to already be in the DB. (No matching "deleted mid-test" proof
     * here, unlike courses — FacultyAccountController currently has no
     * delete/destroy route at all, so there's no real UI path that
     * removes a faculty account to prove against.)
     */
    public function test_stats_faculty_account_count(): void
    {
        $before = User::where('role', 'faculty')->count();

        User::factory()->create(['role' => 'faculty']);
        User::factory()->create(['role' => 'faculty']);
        User::factory()->create(['role' => 'admin']);

        $result = $this->retrieval->retrieve(Type::STATS, 'How many faculty accounts are existing?', role: 'admin');

        $this->assertEmpty($result['courses']);
        $this->assertStringContainsString(($before + 2) . ' faculty accounts', implode(' ', $result['notes']));
    }

    public function test_stats_pending_change_request_count(): void
    {
        $course = Course::query()->first();
        $faculty = User::factory()->create(['role' => 'faculty']);

        CourseChangeRequest::create(['course_id' => $course->id, 'requested_by' => $faculty->id, 'action' => 'update', 'status' => 'pending']);
        CourseChangeRequest::create(['course_id' => $course->id, 'requested_by' => $faculty->id, 'action' => 'update', 'status' => 'pending']);
        CourseChangeRequest::create(['course_id' => $course->id, 'requested_by' => $faculty->id, 'action' => 'delete', 'status' => 'approved']);

        $result = $this->retrieval->retrieve(Type::STATS, 'How many pending course change request?', role: 'intern');

        $this->assertEmpty($result['courses']);
        $this->assertStringContainsString('2 pending course change requests', implode(' ', $result['notes']));
    }

    /**
     * Security requirement (Rico, 2026-08-13): "dapat magkaiba ang
     * information... kay admin/intern sa faculty accounts to protect
     * the system from potential threats or vulnerability." A Faculty
     * account never gets the real count — the query never even runs,
     * see retrieveStats()'s docblock — just a restricted-access marker
     * for ChatbotService to turn into a fixed decline.
     */
    public function test_stats_faculty_account_count_is_restricted_for_faculty_role(): void
    {
        User::factory()->create(['role' => 'faculty']);

        $result = $this->retrieval->retrieve(Type::STATS, 'How many faculty accounts are existing?', role: 'faculty');

        $this->assertEmpty($result['courses']);
        $this->assertCount(1, $result['notes']);
        $this->assertStringStartsWith('ACCESS_RESTRICTED:', $result['notes'][0]);
    }

    public function test_stats_change_request_count_is_restricted_for_faculty_role(): void
    {
        $result = $this->retrieval->retrieve(Type::STATS, 'How many course change requests?', role: 'faculty');

        $this->assertEmpty($result['courses']);
        $this->assertStringStartsWith('ACCESS_RESTRICTED:', $result['notes'][0]);
    }

    // ------------------------------------------------------------------
    // I. Faculty/uploader queries
    // ------------------------------------------------------------------

    /**
     * Regression (2026-08-13): "How many recent uploads?" classifies as
     * FACULTY_UPLOADER (see ChatbotQueryClassifierTest R8) but used to
     * fall to SYLLABUS_AVAILABILITY instead — whose default branch just
     * lists courses WITH a syllabus, nothing about "recent" or a real
     * total. This asserts the retrieval actually reports a total count.
     */
    public function test_faculty_uploader_reports_total_upload_count(): void
    {
        // Baseline BEFORE this test's own addition — the shared dev DB
        // can carry real syllabus uploads from manual browser testing
        // (Rico, 2026-08-13), so "exactly 1" isn't a safe hardcoded
        // assumption; assert the delta instead.
        $before = Syllabus::count();

        $course = Course::query()->first();
        $uploader = User::factory()->create(['role' => 'admin']);

        Syllabus::factory()->create(['course_id' => $course->id, 'uploaded_by' => $uploader->id, 'file_type' => 'pdf']);

        $result = $this->retrieval->retrieve(Type::FACULTY_UPLOADER, 'How many recent uploads?', role: 'admin');

        $this->assertStringContainsString(($before + 1) . ' syllabus files uploaded in total', implode(' ', $result['notes']));
    }

    /** Security requirement — same as the STATS-side restrictions above, applied to system-wide upload activity. */
    public function test_faculty_uploader_recent_uploads_is_restricted_for_faculty_role(): void
    {
        $result = $this->retrieval->retrieve(Type::FACULTY_UPLOADER, 'How many recent uploads?', role: 'faculty');

        $this->assertEmpty($result['courses']);
        $this->assertStringStartsWith('ACCESS_RESTRICTED:', $result['notes'][0]);
    }

    /**
     * A Faculty account CAN still ask about ONE specific course's
     * uploader — that's just browsing a course's own syllabus info,
     * already open to every role (CLAUDE.md §7). Only the system-WIDE
     * views (recent activity, top uploader) are restricted.
     */
    public function test_faculty_uploader_per_course_lookup_not_restricted_for_faculty_role(): void
    {
        $course = Course::where('course_code', 'COMP 016')->first();
        $uploader = User::factory()->create(['role' => 'admin', 'name' => 'Test Uploader']);

        Syllabus::factory()->create(['course_id' => $course->id, 'uploaded_by' => $uploader->id, 'file_type' => 'pdf']);

        $result = $this->retrieval->retrieve(Type::FACULTY_UPLOADER, 'Sino nag-upload ng COMP 016 syllabus?', role: 'faculty');

        $this->assertStringContainsString('Test Uploader', implode(' ', $result['notes']));
    }

    // ------------------------------------------------------------------
    // F. Program comparison
    // ------------------------------------------------------------------

    public function test_program_comparison_reports_both_programs(): void
    {
        // Computed live, not hardcoded — see test_stats_aggregate_totals_
        // bsit_curriculum's docblock above for why.
        $bsitCount = Course::whereHas('program', fn ($q) => $q->where('code', 'BSIT'))->count();
        $ditCount = Course::whereHas('program', fn ($q) => $q->where('code', 'DIT'))->count();

        $result = $this->retrieval->retrieve(Type::PROGRAM_COMPARISON, 'Ano ang pagkakaiba ng BSIT at DIT curriculum?');

        $notesText = implode(' ', $result['notes']);
        $this->assertStringContainsString('BSIT', $notesText);
        $this->assertStringContainsString("{$bsitCount} courses", $notesText);
        $this->assertStringContainsString('DIT', $notesText);
        $this->assertStringContainsString("{$ditCount} courses", $notesText);
    }

    // ------------------------------------------------------------------
    // G. Course category
    // ------------------------------------------------------------------

    public function test_course_category_filters_by_code_prefix(): void
    {
        $program = Course::where('course_code', 'COMP 001')->value('program_id');
        Course::factory()->create(['program_id' => $program, 'course_code' => 'GEED 032', 'title' => 'Life and Works of Rizal']);

        $result = $this->retrieval->retrieve(Type::COURSE_CATEGORY, 'Ano-anong courses ang GEED?');

        $codes = collect($result['courses'])->pluck('course_code');
        $this->assertTrue($codes->contains('GEED 032'));
        $this->assertFalse($codes->contains('COMP 001'));
    }

    public function test_course_category_filters_courses_with_a_lab_component(): void
    {
        $result = $this->retrieval->retrieve(Type::COURSE_CATEGORY, 'Aling courses ang may lab?');

        $codes = collect($result['courses'])->pluck('course_code');
        // COMP 001 has lab_hours = 3 per CourseSeeder; COMP 025 has
        // lab_hours = 0 (lecture-only, "Information Management").
        $this->assertTrue($codes->contains('COMP 001'));
        $this->assertFalse($codes->contains('COMP 025'));
    }

    /**
     * Regression (2026-08-13, live session): "Can you tell me all the
     * courses that starts with the course code 'COMP'?" / "ALL COMP
     * course code?" only ever returned ONE course (COMP 001) — not a
     * real query result, a stale historyFallback() match on whichever
     * course had dominated recent conversation. There was no real "list
     * by prefix" capability for any prefix besides the three hardcoded
     * GEED/NSTP/PATHFIT ones. History here deliberately mentions COMP
     * 001 repeatedly, to prove this is now a real query, not a history
     * guess — it must return every BSIT COMP course, not just one.
     */
    public function test_course_category_lists_all_courses_by_any_real_prefix(): void
    {
        $history = [
            ['role' => 'user', 'content' => 'Ano ang COMP 001?'],
            ['role' => 'assistant', 'content' => 'Ang COMP 001 — Introduction to Computing ay may DOCX at PDF syllabus.'],
        ];

        $result = $this->retrieval->retrieve(Type::COURSE_CATEGORY, 'Can you tell me all the courses that starts with the course code "COMP"?', $history);

        $codes = collect($result['courses'])->pluck('course_code');
        $this->assertGreaterThanOrEqual(13, $codes->count());
        $this->assertTrue($codes->contains('COMP 016'));
        $this->assertTrue($codes->contains('COMP 050'));
        $this->assertFalse($codes->contains('DIT 101'));
    }

    // ------------------------------------------------------------------
    // I. Faculty/uploader
    // ------------------------------------------------------------------

    public function test_faculty_uploader_reports_who_and_when_for_a_specific_course(): void
    {
        $course = Course::where('course_code', 'COMP 016')->firstOrFail();
        $uploader = User::factory()->create(['role' => 'faculty', 'name' => 'Juana Dela Cruz']);
        Syllabus::factory()->create(['course_id' => $course->id, 'file_type' => 'pdf', 'uploaded_by' => $uploader->id]);

        $result = $this->retrieval->retrieve(Type::FACULTY_UPLOADER, 'Sino ang nag-upload ng syllabus ng COMP 016?');

        $notesText = implode(' ', $result['notes']);
        $this->assertStringContainsString('Juana Dela Cruz', $notesText);
    }
}
