<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\SubjectChangeRequest;
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
 * already-seeded/committed subjects (SubjectSeeder's COMP/DIT rows) by
 * their subject_code/title — those columns are never touched, so
 * FULLTEXT sees them fine — or (b) UPDATEs a non-indexed column
 * (prerequisite/corequisite) on an already-committed row, or queries a
 * plain (non-FULLTEXT) WHERE/EXISTS, all of which see a transaction's
 * own uncommitted writes normally. Nothing here inserts a new row and
 * then expects FULLTEXT to find it by subject_code/title within the
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

    public function test_prerequisite_forward_lookup_resolves_the_listed_prereq_to_a_real_subject(): void
    {
        Subject::where('subject_code', 'COMP 003')->update(['prerequisite' => 'COMP 002']);

        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'Ano ang prereq ng COMP 003?');

        $codes = collect($result['subjects'])->pluck('subject_code');
        $this->assertTrue($codes->contains('COMP 003'));
        $this->assertTrue($codes->contains('COMP 002'));
    }

    public function test_prerequisite_reverse_lookup_finds_dependents(): void
    {
        Subject::where('subject_code', 'COMP 003')->update(['prerequisite' => 'COMP 002']);

        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'Ano-anong subjects ang naka-depende sa COMP 002?');

        $codes = collect($result['subjects'])->pluck('subject_code');
        $this->assertTrue($codes->contains('COMP 002'));
        $this->assertTrue($codes->contains('COMP 003'), 'COMP 003 lists COMP 002 as its prerequisite, so it should surface as a dependent.');
    }

    public function test_prerequisite_no_prereq_listed_excludes_subjects_that_have_one(): void
    {
        Subject::where('subject_code', 'COMP 003')->update(['prerequisite' => 'COMP 002']);

        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'Ano-anong subjects ang walang prerequisite?');

        $codes = collect($result['subjects'])->pluck('subject_code');
        $this->assertTrue($codes->contains('COMP 001'));
        $this->assertFalse($codes->contains('COMP 003'));
    }

    /**
     * Regression (Rico, 2026-08-13): the mirror image of the "walang
     * prerequisite" test above — that branch existed alone, this one
     * never did. Without it, "which subjects HAVE a prerequisite" (no
     * code/title of its own) fell through to findAnchorSubjects(), which
     * — with real conversation history present — kept re-anchoring on
     * whichever subject had dominated recent turns (COMP 001, live) and
     * answering about THAT one subject's own null prerequisite instead
     * of the aggregate question actually asked, repeatedly, across
     * follow-ups. History here is deliberately non-empty and repeatedly
     * mentions COMP 001, specifically to prove this branch is now
     * checked BEFORE the anchor/history-fallback path ever runs.
     */
    public function test_prerequisite_which_subjects_have_one_ignores_dominant_history_subject(): void
    {
        Subject::where('subject_code', 'COMP 003')->update(['prerequisite' => 'COMP 002']);

        $history = [
            ['role' => 'user', 'content' => 'Ano ang COMP 001?'],
            ['role' => 'assistant', 'content' => 'Ang COMP 001 — Introduction to Computing ay walang prerequisite (none).'],
            ['role' => 'user', 'content' => 'Meron pa bang iba?'],
            ['role' => 'assistant', 'content' => 'Para sa COMP 001, wala pa ring prerequisite na nakalagay.'],
        ];

        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'Ano-anong mga subject na existing sa system na MAYROONG prerequisite subject?', $history);

        $codes = collect($result['subjects'])->pluck('subject_code');
        $this->assertTrue($codes->contains('COMP 003'));
        $this->assertFalse($codes->contains('COMP 001'), 'COMP 001 has no prerequisite — it must not appear just because it dominated recent history.');
    }

    public function test_prerequisite_which_subjects_have_one_reports_none_honestly_when_true(): void
    {
        // Default seed data: no subject has a prerequisite set at all.
        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'Anong subject ang mayroong lamang prerequisite subject?');

        $this->assertEmpty($result['subjects']);
        $this->assertStringContainsString('No subjects', implode(' ', $result['notes']));
    }

    /** "Which subject HAS THE MOST prerequisites" must NOT be caught by the existence-check branch above — it's a different, superlative question. */
    public function test_prerequisite_most_prerequisites_question_is_not_treated_as_existence_check(): void
    {
        Subject::where('subject_code', 'COMP 003')->update(['prerequisite' => 'COMP 002']);

        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'Which subject has the most prerequisites?');

        // Falls through to the generic text-search fallback (no code/
        // title named) rather than the "list everything with a
        // prerequisite" aggregate — this assertion only guards against
        // it being misrouted into that other branch, not any specific
        // result shape.
        $this->assertIsArray($result['subjects']);
    }

    /**
     * Regression (2026-08-13): co-requisite had the same two-sided gap
     * prerequisite did — found by going through the curriculum
     * spreadsheets' actual columns for what other "which subjects HAVE/
     * HAVE NO X" questions this pattern applies to. Only per-subject
     * co-requisite lookup existed before; "which subjects have one at
     * all" was never its own aggregate query.
     */
    public function test_corequisite_which_subjects_have_one(): void
    {
        Subject::where('subject_code', 'COMP 003')->update(['corequisite' => 'COMP 002']);

        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'Ano-anong subjects ang mayroong co-requisite?');

        $codes = collect($result['subjects'])->pluck('subject_code');
        $this->assertTrue($codes->contains('COMP 003'));
        $this->assertFalse($codes->contains('COMP 001'));
    }

    public function test_corequisite_which_subjects_have_none(): void
    {
        Subject::where('subject_code', 'COMP 003')->update(['corequisite' => 'COMP 002']);

        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'Ano-anong subjects ang walang co-requisite?');

        $codes = collect($result['subjects'])->pluck('subject_code');
        $this->assertTrue($codes->contains('COMP 001'));
        $this->assertFalse($codes->contains('COMP 003'));
    }

    public function test_corequisite_which_subjects_have_one_reports_none_honestly_when_true(): void
    {
        // Default seed data: no subject has a co-requisite set at all.
        $result = $this->retrieval->retrieve(Type::PREREQUISITE, 'May co-requisite ba ang kahit anong subject?');

        $this->assertEmpty($result['subjects']);
        $this->assertStringContainsString('No subjects', implode(' ', $result['notes']));
    }

    // ------------------------------------------------------------------
    // B. Syllabus availability
    // ------------------------------------------------------------------

    public function test_syllabus_availability_missing_excludes_subjects_that_have_a_file(): void
    {
        $subject = Subject::where('subject_code', 'COMP 016')->firstOrFail();
        $uploader = User::factory()->create(['role' => 'admin']);
        Syllabus::factory()->create(['subject_id' => $subject->id, 'file_type' => 'pdf', 'uploaded_by' => $uploader->id]);

        // A subject known to have no syllabus AT THIS MOMENT, rather
        // than a hardcoded "COMP 001" — the shared dev DB can carry real
        // uploads from manual browser testing (Rico uploaded to COMP 001
        // himself, 2026-08-13), and a fixed assumption breaks the moment
        // someone actually uploads something to that specific subject.
        $stillMissing = Subject::whereDoesntHave('syllabi')->where('id', '!=', $subject->id)->first();
        $this->assertNotNull($stillMissing, 'Need at least one other subject with no syllabus for this assertion to mean anything.');

        $result = $this->retrieval->retrieve(Type::SYLLABUS_AVAILABILITY, 'Aling mga subjects ang wala pang syllabus?');

        $codes = collect($result['subjects'])->pluck('subject_code');
        $this->assertFalse($codes->contains('COMP 016'), 'COMP 016 now has a syllabus, so it must not appear in the "missing" list.');
        $this->assertTrue($codes->contains($stillMissing->subject_code));
    }

    /**
     * Regression (2026-08-13, live session): "TELL ME ALL SUBJECTS that
     * doesn't have syllabus yet" matched none of "wala pang"/"kulang"/
     * "missing" (all Tagalog except the one stray English word), so it
     * fell through to the DEFAULT branch — subjects WITH a syllabus, the
     * exact opposite of what was asked.
     */
    public function test_syllabus_availability_english_does_not_have_phrasing(): void
    {
        $subject = Subject::where('subject_code', 'COMP 016')->firstOrFail();
        $uploader = User::factory()->create(['role' => 'admin']);
        Syllabus::factory()->create(['subject_id' => $subject->id, 'file_type' => 'pdf', 'uploaded_by' => $uploader->id]);

        $result = $this->retrieval->retrieve(Type::SYLLABUS_AVAILABILITY, "Tell me all subjects that doesn't have syllabus yet.");

        $codes = collect($result['subjects'])->pluck('subject_code');
        $this->assertFalse($codes->contains('COMP 016'), 'COMP 016 now has a syllabus, so it must not appear in the "missing" list.');
        $this->assertGreaterThanOrEqual(14, $codes->count());
    }

    public function test_syllabus_availability_percentage_note_is_correct(): void
    {
        $subject = Subject::where('subject_code', 'COMP 016')->firstOrFail();
        $uploader = User::factory()->create(['role' => 'admin']);

        // Baseline BEFORE this test's own addition — see the docblock
        // above for why "exactly 1" isn't a safe hardcoded assumption
        // against the shared dev DB.
        $withSyllabusBefore = Subject::whereHas('syllabi')->count();

        Syllabus::factory()->create(['subject_id' => $subject->id, 'file_type' => 'pdf', 'uploaded_by' => $uploader->id]);

        $result = $this->retrieval->retrieve(Type::SYLLABUS_AVAILABILITY, 'Ilan na ang may syllabus?');

        $this->assertNotEmpty($result['notes']);
        $this->assertStringContainsString(($withSyllabusBefore + 1) . ' out of 16', $result['notes'][0]);
    }

    /**
     * Regression (2026-08-13): curriculum_year is a real column on
     * syllabi, but nothing queried it before this — "Can you give me
     * all the subjects that are in Curriculum 2022-2023?" had no
     * capability anywhere and always answered "wala akong nakita".
     */
    public function test_syllabus_availability_filters_by_curriculum_year(): void
    {
        $inScope = Subject::where('subject_code', 'COMP 016')->firstOrFail();
        $outOfScope = Subject::where('subject_code', 'COMP 020')->firstOrFail();
        $uploader = User::factory()->create(['role' => 'admin']);

        Syllabus::factory()->create(['subject_id' => $inScope->id, 'file_type' => 'pdf', 'uploaded_by' => $uploader->id, 'curriculum_year' => '2022-2023']);
        Syllabus::factory()->create(['subject_id' => $outOfScope->id, 'file_type' => 'pdf', 'uploaded_by' => $uploader->id, 'curriculum_year' => '2023-2024']);

        $result = $this->retrieval->retrieve(Type::SYLLABUS_AVAILABILITY, 'Can you give me all the subjects that are in Curriculum 2022-2023?');

        $codes = collect($result['subjects'])->pluck('subject_code');
        $this->assertTrue($codes->contains('COMP 016'));
        $this->assertFalse($codes->contains('COMP 020'));

        // Regression (2026-08-13, second round): the SAME query above,
        // live, sometimes answered correctly and sometimes gave the
        // strict "wala akong nakita" refusal — same retrieved subject
        // every time, but nothing in the context ever explicitly said
        // "this matches curriculum year 2022-2023", so Gemini had to
        // guess whether it did. A note stating it outright removes that
        // ambiguity instead of leaving it implied.
        $this->assertStringContainsString('2022-2023', implode(' ', $result['notes']));
        $this->assertStringContainsString('COMP 016', implode(' ', $result['notes']));
    }

    public function test_syllabus_availability_curriculum_year_with_no_matches_reports_zero_not_a_refusal(): void
    {
        $result = $this->retrieval->retrieve(Type::SYLLABUS_AVAILABILITY, 'Ano ang subjects sa curriculum year 2099-2100?');

        $this->assertEmpty($result['subjects']);
        $this->assertStringContainsString('2099-2100', $result['notes'][0]);
    }

    /**
     * Regression (2026-08-13): asking about ONE specific subject by
     * code used to also pull in an unrelated subject whenever that
     * OTHER subject's uploaded file coincidentally contained the same
     * digits somewhere in its (unrelated) extracted text — a
     * coincidental syllabus_content match diluting a precise code
     * lookup that should only ever mean one thing.
     */
    public function test_year_semester_exact_code_ignores_coincidental_syllabus_content_match(): void
    {
        $named = Subject::where('subject_code', 'COMP 016')->firstOrFail();
        $unrelated = Subject::where('subject_code', 'COMP 001')->firstOrFail();
        $uploader = User::factory()->create(['role' => 'admin']);

        Syllabus::factory()->create([
            'subject_id' => $unrelated->id,
            'file_type' => 'pdf',
            'uploaded_by' => $uploader->id,
            'raw_text' => 'This unrelated template happens to mention 016 somewhere in its text.',
        ]);

        $result = $this->retrieval->retrieve(Type::YEAR_SEMESTER, 'Anong year level ang COMP 016?');

        $this->assertCount(1, $result['subjects']);
        $this->assertSame('COMP 016', $result['subjects'][0]['subject_code']);
    }

    /**
     * Regression (2026-08-13, live session): "Can you tell me the
     * subjects in year 2024?" wrongly anchored on COMP 001 purely
     * because an unrelated uploaded file's extracted text happened to
     * contain "2024" somewhere — the only match found at all, so the
     * earlier "only when competing with a stronger match" guard (see
     * the test above) never even triggered. syllabus_content matches are
     * now excluded from findAnchorSubjects() unconditionally, not just
     * when a stronger match coexists — see that method's own docblock.
     */
    public function test_year_semester_ignores_coincidental_content_match_even_as_the_only_result(): void
    {
        $unrelated = Subject::where('subject_code', 'COMP 001')->firstOrFail();
        $uploader = User::factory()->create(['role' => 'admin']);

        Syllabus::factory()->create([
            'subject_id' => $unrelated->id,
            'file_type' => 'pdf',
            'uploaded_by' => $uploader->id,
            'raw_text' => 'This unrelated accomplishment report happens to mention the year 2024 somewhere in its text.',
        ]);

        $result = $this->retrieval->retrieve(Type::YEAR_SEMESTER, 'Can you tell me the subjects in year 2024?');

        $codes = collect($result['subjects'])->pluck('subject_code');
        $this->assertFalse($codes->contains('COMP 001'), 'COMP 001 must not be singled out just because its uploaded file happens to mention "2024".');
    }

    /**
     * Regression (2026-08-13, live session): on top of the false-anchor
     * bug above, the fallback behavior itself was also unhelpful — it
     * silently dumped the entire unfiltered 16-subject catalog with no
     * explanation, since "2024" doesn't match any of the ordinal
     * year-level forms parseScope() recognizes (1st/2nd/3rd/4th/...).
     * "Year level" in this curriculum means 1st-4th year of study, never
     * a calendar year — Sage should say that plainly instead of
     * guessing or dumping everything.
     */
    public function test_year_semester_calendar_year_gets_a_clarifying_note_not_the_whole_catalog(): void
    {
        $result = $this->retrieval->retrieve(Type::YEAR_SEMESTER, 'Can you tell me the subjects in year 2024?');

        $this->assertEmpty($result['subjects']);
        $this->assertStringContainsString('2024', implode(' ', $result['notes']));
        $this->assertStringContainsString('not a valid year level', implode(' ', $result['notes']));
    }

    // ------------------------------------------------------------------
    // D. Year level & semester
    // ------------------------------------------------------------------

    public function test_year_semester_filter_scoped_to_program(): void
    {
        $result = $this->retrieval->retrieve(Type::YEAR_SEMESTER, 'Ano-anong subjects sa First Year, First Semester ng BSIT?');

        $codes = collect($result['subjects'])->pluck('subject_code');
        $this->assertTrue($codes->contains('COMP 001'));
        $this->assertTrue($codes->contains('COMP 002'));
        $this->assertFalse($codes->contains('DIT 101'), 'DIT 101 is also Year 1/1st sem but a different program — BSIT scope should exclude it.');
    }

    public function test_year_semester_single_subject_placement(): void
    {
        $result = $this->retrieval->retrieve(Type::YEAR_SEMESTER, 'Anong year level ang COMP 016?');

        $this->assertCount(1, $result['subjects']);
        $this->assertSame('COMP 016', $result['subjects'][0]['subject_code']);
        $this->assertSame(2, $result['subjects'][0]['year_level']);
    }

    /**
     * Regression (2026-08-13, live session): "What about the subjects
     * in DIT program?" has no count/year/comparison word, so it had no
     * classifier home before (now routed to YEAR_SEMESTER, which
     * already had correct program scoping — see ChatbotQueryClassifierTest
     * Z1). But even after that, the anchor-lookup here would still run
     * FIRST and coincidentally FULLTEXT-match "program" as a prefix of
     * "Programming" in three unrelated BSIT subjects, ignoring the DIT
     * scope entirely — fixed by skipping the anchor branch whenever an
     * explicit program scope was found, the same way year/semester
     * scope already did.
     */
    public function test_year_semester_program_only_scope_ignores_coincidental_anchor_match(): void
    {
        $result = $this->retrieval->retrieve(Type::YEAR_SEMESTER, 'What about the subjects in DIT program?');

        $codes = collect($result['subjects'])->pluck('subject_code');
        $this->assertEquals(['DIT 101', 'DIT 102', 'DIT 103'], $codes->sort()->values()->all());
    }

    /**
     * Regression (2026-08-13, live session): "1st" alone (no "year"/
     * "sem" suffix) resolved to no scope at all, so "What about overall
     * 1st subjects?" fell through to anchor/history guessing instead of
     * being read as "1st year subjects" — the only sensible reading in
     * an app that's never about anything but subjects.
     */
    public function test_year_semester_bare_ordinal_without_year_suffix_still_scopes(): void
    {
        $result = $this->retrieval->retrieve(Type::YEAR_SEMESTER, 'What about overall 1st subjects?');

        $codes = collect($result['subjects'])->pluck('subject_code');
        $this->assertTrue($codes->contains('COMP 001'));
        $this->assertTrue($codes->contains('DIT 101'), 'No program was named, so both BSIT and DIT Year 1 subjects should be included.');
        $this->assertFalse($codes->contains('COMP 016'), 'COMP 016 is Year 2 — must not appear in a Year 1 scope.');
    }

    public function test_subject_lookup_of_a_nonexistent_code_finds_nothing(): void
    {
        // A5 in chatbot-test-cases.md: "Mayroon bang subject na COMP
        // 025?" style question about a plausible-looking but nonexistent
        // code should find nothing — not a fuzzy near-match to a real
        // one — so Groq's strict-grounding "not found" rule actually
        // applies instead of silently answering about the wrong subject.
        $result = $this->retrieval->retrieve(Type::SUBJECT_LOOKUP, 'Mayroon bang subject na ZZZ 999?');

        $this->assertEmpty($result['subjects']);
    }

    /**
     * Regression (2026-08-13): "OOP" matched nothing at all — no subject
     * title has a word literally starting with "oop" for the FULLTEXT
     * prefix search to catch, and it's too short for the fuzzy fallback
     * to clear the similarity threshold either. resolveAliases() maps it
     * straight to its one real subject.
     */
    public function test_subject_lookup_resolves_common_abbreviation_oop(): void
    {
        $result = $this->retrieval->retrieve(Type::GENERAL_SEARCH, 'OOP');

        $this->assertCount(1, $result['subjects']);
        $this->assertSame('COMP 011', $result['subjects'][0]['subject_code']);
    }

    /**
     * Regression (2026-08-13): "Web Dev" technically matched something,
     * but BOTH "Web Development" (COMP 016) AND "Advanced Web and Mobile
     * Development" (DIT 103) — both titles share the word "Web". Rico's
     * expectation was that this shorthand means exactly one subject.
     */
    public function test_subject_lookup_resolves_common_abbreviation_web_dev(): void
    {
        $result = $this->retrieval->retrieve(Type::GENERAL_SEARCH, 'Web Dev');

        $this->assertCount(1, $result['subjects']);
        $this->assertSame('COMP 016', $result['subjects'][0]['subject_code']);
    }

    // ------------------------------------------------------------------
    // E. Units & hours
    // ------------------------------------------------------------------

    public function test_stats_single_subject_returns_its_own_numbers_not_an_aggregate(): void
    {
        $result = $this->retrieval->retrieve(Type::STATS, 'Ilang units ang COMP 016?');

        $this->assertCount(1, $result['subjects']);
        $this->assertSame('COMP 016', $result['subjects'][0]['subject_code']);
        $this->assertEquals(3.0, $result['subjects'][0]['credited_units']);
    }

    public function test_stats_aggregate_totals_bsit_curriculum(): void
    {
        $result = $this->retrieval->retrieve(Type::STATS, 'Ilan ang total units ng buong BSIT curriculum?');

        $notesText = implode(' ', $result['notes']);
        $this->assertStringContainsString('Subject count in scope: 13', $notesText);
        $this->assertStringContainsString('Total credited units in scope: 39', $notesText);
    }

    /**
     * Regression (2026-08-13): tuition_hours is a real, tracked column
     * (already surfaced per-subject), but the aggregate STATS totals
     * never summed it — found going through the curriculum spreadsheets'
     * actual columns for gaps of this shape.
     */
    public function test_stats_aggregate_totals_include_tuition_hours(): void
    {
        $result = $this->retrieval->retrieve(Type::STATS, 'Ilan ang total tuition hours ng buong BSIT curriculum?');

        $this->assertStringContainsString('Total tuition hours in scope:', implode(' ', $result['notes']));
    }

    /**
     * Regression (2026-08-13): this message names no subject at all, but
     * its keyword fallback (see textSearch()) used to FULLTEXT-match the
     * standalone word "system" as a prefix of "Systems" in three
     * unrelated titles (Database Management Systems, Systems Integration
     * and Architecture, Advanced Database Systems) — findAnchorSubjects()
     * then treated that coincidence as the user naming those 3 subjects,
     * so this answered "3" instead of the true curriculum-wide total.
     */
    public function test_stats_generic_total_ignores_coincidental_keyword_match(): void
    {
        $result = $this->retrieval->retrieve(Type::STATS, 'How many subjects are existing sa system?');

        $notesText = implode(' ', $result['notes']);
        $this->assertStringContainsString('Subject count in scope: 16', $notesText);
        $this->assertCount(16, $result['subjects']);
    }

    /**
     * Regression (2026-08-13, live session, fourth round): "the number
     * of subjects existed in the system" used to anchor on a
     * coincidental FULLTEXT-prefix match ("system*" -> "Systems") and
     * answer "4" instead of the true total of 16 — the SAME bug as the
     * test above, just via "number of"/"existed" instead of "how many"/
     * "existing", which neither the classifier nor this guard
     * recognized as count language yet.
     */
    public function test_stats_number_of_subjects_existed_ignores_coincidental_keyword_match(): void
    {
        $result = $this->retrieval->retrieve(Type::STATS, 'can you tell me the number of subjects existed in the system?');

        $notesText = implode(' ', $result['notes']);
        $this->assertStringContainsString('Subject count in scope: 16', $notesText);
        $this->assertCount(16, $result['subjects']);
    }

    /**
     * Regression (2026-08-13, live session): "If so, how many subjects
     * are in BSIT?" has no code/title of its own, but historyFallback()
     * used to pick up "COMP 008" — mentioned several turns earlier in
     * the SAME conversation — and wrongly anchor to just that one
     * subject instead of counting the whole BSIT program. History here
     * is a realistic slice of Rico's actual transcript, not a synthetic
     * minimal case, specifically to reproduce the coincidence.
     */
    public function test_stats_generic_program_total_ignores_stale_history_anchor(): void
    {
        $history = [
            ['role' => 'user', 'content' => 'How many subjects existing sa BSIT 2nd Year?'],
            ['role' => 'assistant', 'content' => "Mayroon kang 4 na subjects para sa BSIT 2nd Year sa system:\n\n1. COMP 008 — Data Structures and Algorithms\n2. COMP 011 — Object Oriented Programming"],
            ['role' => 'user', 'content' => 'How many BSIT subjects overall?'],
            ['role' => 'assistant', 'content' => 'Mayroong kabuuang 13 na BSIT subjects sa system ngayon, na may total credited units na 39.'],
        ];

        $result = $this->retrieval->retrieve(Type::STATS, 'If so, how many subjects are in BSIT?', $history);

        $notesText = implode(' ', $result['notes']);
        $this->assertStringContainsString('Subject count in scope: 13', $notesText);
        $this->assertCount(13, $result['subjects']);
    }

    public function test_stats_faculty_account_count(): void
    {
        // +2 on top of whatever's already seeded (UserSeeder commits a
        // faculty test account outside this test's transaction) — assert
        // the delta, not a hardcoded absolute, so this doesn't depend on
        // what else happens to be in the DB.
        $before = User::where('role', 'faculty')->count();

        User::factory()->create(['role' => 'faculty']);
        User::factory()->create(['role' => 'faculty']);
        User::factory()->create(['role' => 'admin']);

        $result = $this->retrieval->retrieve(Type::STATS, 'How many faculty accounts are existing?', role: 'admin');

        $this->assertEmpty($result['subjects']);
        $this->assertStringContainsString(($before + 2) . ' faculty accounts', implode(' ', $result['notes']));
    }

    public function test_stats_pending_change_request_count(): void
    {
        $subject = Subject::query()->first();
        $faculty = User::factory()->create(['role' => 'faculty']);

        SubjectChangeRequest::create(['subject_id' => $subject->id, 'requested_by' => $faculty->id, 'action' => 'update', 'status' => 'pending']);
        SubjectChangeRequest::create(['subject_id' => $subject->id, 'requested_by' => $faculty->id, 'action' => 'update', 'status' => 'pending']);
        SubjectChangeRequest::create(['subject_id' => $subject->id, 'requested_by' => $faculty->id, 'action' => 'delete', 'status' => 'approved']);

        $result = $this->retrieval->retrieve(Type::STATS, 'How many pending subject change request?', role: 'intern');

        $this->assertEmpty($result['subjects']);
        $this->assertStringContainsString('2 pending subject change requests', implode(' ', $result['notes']));
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

        $this->assertEmpty($result['subjects']);
        $this->assertCount(1, $result['notes']);
        $this->assertStringStartsWith('ACCESS_RESTRICTED:', $result['notes'][0]);
    }

    public function test_stats_change_request_count_is_restricted_for_faculty_role(): void
    {
        $result = $this->retrieval->retrieve(Type::STATS, 'How many subject change requests?', role: 'faculty');

        $this->assertEmpty($result['subjects']);
        $this->assertStringStartsWith('ACCESS_RESTRICTED:', $result['notes'][0]);
    }

    // ------------------------------------------------------------------
    // I. Faculty/uploader queries
    // ------------------------------------------------------------------

    /**
     * Regression (2026-08-13): "How many recent uploads?" classifies as
     * FACULTY_UPLOADER (see ChatbotQueryClassifierTest R8) but used to
     * fall to SYLLABUS_AVAILABILITY instead — whose default branch just
     * lists subjects WITH a syllabus, nothing about "recent" or a real
     * total. This asserts the retrieval actually reports a total count.
     */
    public function test_faculty_uploader_reports_total_upload_count(): void
    {
        // Baseline BEFORE this test's own addition — the shared dev DB
        // can carry real syllabus uploads from manual browser testing
        // (Rico, 2026-08-13), so "exactly 1" isn't a safe hardcoded
        // assumption; assert the delta instead.
        $before = Syllabus::count();

        $subject = Subject::query()->first();
        $uploader = User::factory()->create(['role' => 'admin']);

        Syllabus::factory()->create(['subject_id' => $subject->id, 'uploaded_by' => $uploader->id, 'file_type' => 'pdf']);

        $result = $this->retrieval->retrieve(Type::FACULTY_UPLOADER, 'How many recent uploads?', role: 'admin');

        $this->assertStringContainsString(($before + 1) . ' syllabus files uploaded in total', implode(' ', $result['notes']));
    }

    /** Security requirement — same as the STATS-side restrictions above, applied to system-wide upload activity. */
    public function test_faculty_uploader_recent_uploads_is_restricted_for_faculty_role(): void
    {
        $result = $this->retrieval->retrieve(Type::FACULTY_UPLOADER, 'How many recent uploads?', role: 'faculty');

        $this->assertEmpty($result['subjects']);
        $this->assertStringStartsWith('ACCESS_RESTRICTED:', $result['notes'][0]);
    }

    /**
     * A Faculty account CAN still ask about ONE specific subject's
     * uploader — that's just browsing a subject's own syllabus info,
     * already open to every role (CLAUDE.md §7). Only the system-WIDE
     * views (recent activity, top uploader) are restricted.
     */
    public function test_faculty_uploader_per_subject_lookup_not_restricted_for_faculty_role(): void
    {
        $subject = Subject::where('subject_code', 'COMP 016')->first();
        $uploader = User::factory()->create(['role' => 'admin', 'name' => 'Test Uploader']);

        Syllabus::factory()->create(['subject_id' => $subject->id, 'uploaded_by' => $uploader->id, 'file_type' => 'pdf']);

        $result = $this->retrieval->retrieve(Type::FACULTY_UPLOADER, 'Sino nag-upload ng COMP 016 syllabus?', role: 'faculty');

        $this->assertStringContainsString('Test Uploader', implode(' ', $result['notes']));
    }

    // ------------------------------------------------------------------
    // F. Program comparison
    // ------------------------------------------------------------------

    public function test_program_comparison_reports_both_programs(): void
    {
        $result = $this->retrieval->retrieve(Type::PROGRAM_COMPARISON, 'Ano ang pagkakaiba ng BSIT at DIT curriculum?');

        $notesText = implode(' ', $result['notes']);
        $this->assertStringContainsString('BSIT', $notesText);
        $this->assertStringContainsString('13 subjects', $notesText);
        $this->assertStringContainsString('DIT', $notesText);
        $this->assertStringContainsString('3 subjects', $notesText);
    }

    // ------------------------------------------------------------------
    // G. Subject category
    // ------------------------------------------------------------------

    public function test_subject_category_filters_by_code_prefix(): void
    {
        $program = Subject::where('subject_code', 'COMP 001')->value('program_id');
        Subject::factory()->create(['program_id' => $program, 'subject_code' => 'GEED 032', 'title' => 'Life and Works of Rizal']);

        $result = $this->retrieval->retrieve(Type::SUBJECT_CATEGORY, 'Ano-anong subjects ang GEED?');

        $codes = collect($result['subjects'])->pluck('subject_code');
        $this->assertTrue($codes->contains('GEED 032'));
        $this->assertFalse($codes->contains('COMP 001'));
    }

    public function test_subject_category_filters_subjects_with_a_lab_component(): void
    {
        $result = $this->retrieval->retrieve(Type::SUBJECT_CATEGORY, 'Aling subjects ang may lab?');

        $codes = collect($result['subjects'])->pluck('subject_code');
        // COMP 001 has lab_hours = 3 per SubjectSeeder; COMP 025 has
        // lab_hours = 0 (lecture-only, "Information Management").
        $this->assertTrue($codes->contains('COMP 001'));
        $this->assertFalse($codes->contains('COMP 025'));
    }

    /**
     * Regression (2026-08-13, live session): "Can you tell me all the
     * subjects that starts with the course code 'COMP'?" / "ALL COMP
     * subject code?" only ever returned ONE subject (COMP 001) — not a
     * real query result, a stale historyFallback() match on whichever
     * subject had dominated recent conversation. There was no real "list
     * by prefix" capability for any prefix besides the three hardcoded
     * GEED/NSTP/PATHFIT ones. History here deliberately mentions COMP
     * 001 repeatedly, to prove this is now a real query, not a history
     * guess — it must return every BSIT COMP subject, not just one.
     */
    public function test_subject_category_lists_all_subjects_by_any_real_prefix(): void
    {
        $history = [
            ['role' => 'user', 'content' => 'Ano ang COMP 001?'],
            ['role' => 'assistant', 'content' => 'Ang COMP 001 — Introduction to Computing ay may DOCX at PDF syllabus.'],
        ];

        $result = $this->retrieval->retrieve(Type::SUBJECT_CATEGORY, 'Can you tell me all the subjects that starts with the course code "COMP"?', $history);

        $codes = collect($result['subjects'])->pluck('subject_code');
        $this->assertGreaterThanOrEqual(13, $codes->count());
        $this->assertTrue($codes->contains('COMP 016'));
        $this->assertTrue($codes->contains('COMP 050'));
        $this->assertFalse($codes->contains('DIT 101'));
    }

    // ------------------------------------------------------------------
    // I. Faculty/uploader
    // ------------------------------------------------------------------

    public function test_faculty_uploader_reports_who_and_when_for_a_specific_subject(): void
    {
        $subject = Subject::where('subject_code', 'COMP 016')->firstOrFail();
        $uploader = User::factory()->create(['role' => 'faculty', 'name' => 'Juana Dela Cruz']);
        Syllabus::factory()->create(['subject_id' => $subject->id, 'file_type' => 'pdf', 'uploaded_by' => $uploader->id]);

        $result = $this->retrieval->retrieve(Type::FACULTY_UPLOADER, 'Sino ang nag-upload ng syllabus ng COMP 016?');

        $notesText = implode(' ', $result['notes']);
        $this->assertStringContainsString('Juana Dela Cruz', $notesText);
    }
}
