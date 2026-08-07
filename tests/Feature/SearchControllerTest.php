<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Subject;
use App\Models\Syllabus;
use App\Models\User;
use Tests\TestCase;

/**
 * "Ask SyllabiHub" — the priority feature. Tests run against the real
 * MySQL connection (see phpunit.xml), not SQLite, because MATCH...AGAINST
 * FULLTEXT queries have no SQLite equivalent.
 *
 * Deliberately NOT using DatabaseTransactions here: InnoDB FULLTEXT
 * indexes only see committed rows — a row inserted inside an uncommitted
 * transaction never shows up in a MATCH()...AGAINST() search, even from
 * the same transaction. So fixtures are created and committed for real,
 * then explicitly force-deleted in tearDown().
 *
 * To avoid leaking rows via nested factory relationships (Subject's
 * program_id, Syllabus's uploaded_by) — which would commit for real too
 * and never get cleaned up — helpers below reuse an existing Program/User
 * instead of creating throwaway ones. Only Subject/Syllabus rows (the
 * things these tests actually exercise) get created and tracked.
 */
class SearchControllerTest extends TestCase
{
    private array $subjectIds = [];

    protected function tearDown(): void
    {
        Syllabus::withTrashed()->whereIn('subject_id', $this->subjectIds)->forceDelete();
        Subject::withTrashed()->whereIn('id', $this->subjectIds)->forceDelete();

        parent::tearDown();
    }

    private function makeSubject(array $attributes = []): Subject
    {
        $programId = Program::query()->value('id') ?? Program::factory()->create()->id;

        $subject = Subject::factory()->create(array_merge(['program_id' => $programId], $attributes));
        $this->subjectIds[] = $subject->id;

        return $subject;
    }

    private function existingUserId(): int
    {
        return User::query()->value('id') ?? User::factory()->create()->id;
    }

    public function test_exact_subject_code_match_via_fulltext(): void
    {
        $subject = $this->makeSubject([
            'subject_code' => 'TST 016',
            'title' => 'Web Development Fixture',
        ]);

        $response = $this->getJson('/api/search?q=' . urlencode('TST 016'));

        $response->assertOk();
        $response->assertJsonPath('results.0.subject_id', $subject->id);
        $response->assertJsonPath('results.0.match_type', 'fulltext');
    }

    public function test_code_typed_without_space_still_resolves_via_fulltext(): void
    {
        $subject = $this->makeSubject([
            'subject_code' => 'TST 016',
            'title' => 'Web Development Fixture',
        ]);

        $response = $this->getJson('/api/search?q=tst016');

        $response->assertOk();
        $response->assertJsonCount(1, 'results');
        $response->assertJsonPath('results.0.subject_id', $subject->id);
    }

    public function test_partial_word_matches_like_autocomplete(): void
    {
        $subject = $this->makeSubject(['title' => 'Advanced Networking Concepts']);

        $response = $this->getJson('/api/search?q=network');

        $response->assertOk();
        $response->assertJsonFragment(['subject_id' => $subject->id]);
    }

    public function test_typo_falls_back_to_fuzzy_match(): void
    {
        $subject = $this->makeSubject([
            'subject_code' => 'TST 099',
            'title' => 'Database Fundamentals',
        ]);

        $response = $this->getJson('/api/search?q=' . urlencode('databse fundamentals'));

        $response->assertOk();
        $response->assertJsonFragment(['subject_id' => $subject->id]);
        $response->assertJsonPath('results.0.match_type', 'fuzzy');
    }

    public function test_syllabus_content_match_surfaces_a_subject_with_no_title_match(): void
    {
        $subject = $this->makeSubject(['title' => 'Special Topics in Computing']);

        Syllabus::factory()->create([
            'subject_id' => $subject->id,
            'uploaded_by' => $this->existingUserId(),
            'raw_text' => 'This course goes deep into container orchestration and kubernetes clusters.',
            'status' => 'processed',
        ]);

        $response = $this->getJson('/api/search?q=' . urlencode('kubernetes clusters'));

        $response->assertOk();
        $response->assertJsonPath('results.0.subject_id', $subject->id);
        $response->assertJsonPath('results.0.match_type', 'syllabus_content');
        $this->assertNotNull($response->json('results.0.snippet'));
    }

    public function test_empty_query_returns_no_results(): void
    {
        $response = $this->getJson('/api/search?q=');

        $response->assertOk();
        $response->assertJson(['query' => '', 'count' => 0, 'results' => []]);
    }

    public function test_overlong_query_is_rejected_with_validation_error(): void
    {
        $response = $this->getJson('/api/search?q=' . str_repeat('a', 300));

        $response->assertStatus(422);
    }
}
