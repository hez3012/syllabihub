<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\Syllabus;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * "Sage" chatbot, end-to-end through POST /api/chat.
 * Groq is always Http::fake()'d — these tests never make a real
 * network call, so they don't burn free-tier quota and don't need a
 * real GROQ_API_KEY to run. See ChatbotQueryClassifierTest (pure unit,
 * no DB) and ChatbotRetrievalServiceTest (DB, no Groq) for the
 * classify/retrieve logic itself — this file is about the full pipeline
 * and the HTTP contract.
 *
 * DatabaseTransactions is safe here for the same reason it is in
 * ChatbotRetrievalServiceTest — see that file's docblock.
 */
class ChatbotControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'faculty']));
        config(['services.groq.key' => 'fake-test-key']);

        // The global groq-per-minute/groq-per-day limiters (see
        // AppServiceProvider) key by a fixed 'global' string, not per
        // user/IP — clear them so one test's calls don't count against
        // the next test's budget.
        RateLimiter::clear('groq-per-minute');
        RateLimiter::clear('groq-per-day');
    }

    private function fakeGroqReply(string $text): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => $text]],
                ],
            ]),
        ]);
    }

    public function test_guest_cannot_use_the_chat_endpoint(): void
    {
        auth()->logout();

        $response = $this->postJson('/api/chat', ['message' => 'hello']);

        $response->assertUnauthorized();
    }

    public function test_message_is_required(): void
    {
        $response = $this->postJson('/api/chat', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('message');
    }

    public function test_response_contract_matches_answer_sources_query_type(): void
    {
        $this->fakeGroqReply('Meron pong available na syllabus para sa COMP 016 — Web Development.');

        $response = $this->postJson('/api/chat', ['message' => 'Ano ang COMP 016?']);

        $response->assertOk();
        $response->assertJsonStructure(['answer', 'sources', 'query_type']);
        $response->assertJsonPath('query_type', 'subject_lookup');
        $response->assertJsonPath('answer', 'Meron pong available na syllabus para sa COMP 016 — Web Development.');
        $response->assertJsonFragment(['subject_code' => 'COMP 016']);
    }

    public function test_sources_include_subject_code_title_and_has_syllabus(): void
    {
        $subject = Subject::where('subject_code', 'COMP 001')->firstOrFail();
        $uploader = User::factory()->create(['role' => 'admin']);
        Syllabus::factory()->create(['subject_id' => $subject->id, 'file_type' => 'pdf', 'uploaded_by' => $uploader->id]);

        $this->fakeGroqReply('Oo, may PDF na available para sa COMP 001.');

        $response = $this->postJson('/api/chat', ['message' => 'Ano ang COMP 001?']);

        $response->assertOk();
        $source = collect($response->json('sources'))->firstWhere('subject_code', 'COMP 001');

        $this->assertNotNull($source);
        $this->assertSame('COMP 001', $source['subject_code']);
        $this->assertTrue($source['has_syllabus']);
        $this->assertSame('pdf', $source['syllabus_files'][0]['file_type']);
    }

    public function test_groq_receives_query_type_and_retrieved_context(): void
    {
        $this->fakeGroqReply('...');

        $this->postJson('/api/chat', ['message' => 'Ano ang prereq ng COMP 003?'])->assertOk();

        Http::assertSent(function ($request) {
            $text = collect($request['messages'])->last()['content'];

            return str_contains($text, 'Query type: prerequisite');
        });
    }

    public function test_greeting_does_not_report_no_results_found(): void
    {
        $this->fakeGroqReply('Hello! How can I help you find a subject or syllabus today?');

        $response = $this->postJson('/api/chat', ['message' => 'Hello']);

        $response->assertOk();
        $response->assertJsonPath('query_type', 'greeting');

        Http::assertSent(function ($request) {
            $text = collect($request['messages'])->last()['content'];

            return str_contains($text, 'Query type: greeting') && str_contains($text, 'no matching data found');
        });
    }

    /**
     * Missing GROQ_API_KEY — reply() must degrade to the fallback
     * answer instead of throwing/erroring, per Rico 2026-08-12
     * ("mag-fallback sa non-AI response... i-return ang raw search
     * results"). Still a 200, not a 503 — the frontend shouldn't need a
     * special error branch for an expected, handled case.
     */
    public function test_missing_api_key_falls_back_to_raw_search_results(): void
    {
        config(['services.groq.key' => null]);

        $response = $this->postJson('/api/chat', ['message' => 'Ano ang COMP 016?']);

        $response->assertOk();
        $response->assertJsonPath('answer', 'Pasensya, hindi available ang AI assistant ngayon. Narito ang mga nahanap ko sa database:');
        $response->assertJsonFragment(['subject_code' => 'COMP 016']);
        Http::assertNothingSent();
    }

    public function test_groq_failure_falls_back_to_raw_search_results(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $response = $this->postJson('/api/chat', ['message' => 'Ano ang COMP 016?']);

        $response->assertOk();
        $response->assertJsonPath('answer', 'Pasensya, hindi available ang AI assistant ngayon. Narito ang mga nahanap ko sa database:');
        $response->assertJsonFragment(['subject_code' => 'COMP 016']);
    }

    /**
     * Security requirement (Rico, 2026-08-13): a Faculty account asking
     * about privileged system data (faculty accounts, change requests,
     * system-wide upload activity) gets a fixed decline — and Groq is
     * never even called, so there's no LLM step that could be talked
     * into repeating data it was never given (setUp() already
     * actingAs()'s a faculty user, so no override needed here).
     */
    public function test_faculty_role_gets_a_fixed_decline_for_privileged_data_no_groq_call(): void
    {
        $response = $this->postJson('/api/chat', ['message' => 'How many faculty accounts are existing?']);

        $response->assertOk();
        $this->assertStringContainsString('Admin at Intern accounts', $response->json('answer'));
        $this->assertEmpty($response->json('sources'));
        Http::assertNothingSent();
    }

    /** Same privileged question, but from an Admin — gets the real answer, and Groq IS called this time. */
    public function test_admin_role_gets_the_real_answer_for_privileged_data(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->fakeGroqReply('Mayroong 1 faculty account sa system.');

        $response = $this->postJson('/api/chat', ['message' => 'How many faculty accounts are existing?']);

        $response->assertOk();
        $response->assertJsonPath('answer', 'Mayroong 1 faculty account sa system.');
        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'faculty account'));
    }

    /**
     * Regression test for a real bug (2026-08-12): asking about one
     * specific subject by its compound code ("COMP 001") was flooding
     * the reply with nearly every BSIT subject.
     */
    public function test_asking_about_one_subject_does_not_flood_the_whole_program_catalog(): void
    {
        $this->fakeGroqReply('Meron pong available na syllabus para sa COMP 001.');

        $response = $this->postJson('/api/chat', ['message' => 'Can you send me the file of the COMP 001?']);

        $response->assertOk();
        $sources = $response->json('sources');

        $this->assertCount(1, $sources);
        $this->assertSame('COMP 001', $sources[0]['subject_code']);
    }

    /**
     * Regression test for a real bug (2026-08-12): a follow-up question
     * that only makes sense together with what was just discussed
     * ("Ilan ang credit units nito?") found nothing on its own.
     */
    public function test_pronoun_follow_up_resolves_to_the_subject_named_in_recent_history(): void
    {
        $this->fakeGroqReply('COMP 001 is worth 3 credited units.');

        $response = $this->postJson('/api/chat', [
            'message' => 'Ilan ang credit units nito?',
            'history' => [
                ['role' => 'user', 'content' => 'Meron bang COMP 001?'],
                ['role' => 'assistant', 'content' => 'Yes, meron pong COMP 001 — Introduction to Computing.'],
            ],
        ]);

        $response->assertOk();
        $sources = $response->json('sources');

        $this->assertCount(1, $sources);
        $this->assertSame('COMP 001', $sources[0]['subject_code']);
    }

    public function test_chat_endpoint_is_rate_limited_globally(): void
    {
        $this->fakeGroqReply('...');

        // The groq-per-minute limiter (AppServiceProvider) allows 25
        // requests/min across ALL users combined, not per user — so 25
        // calls from this one test user should already exhaust it.
        for ($i = 0; $i < 25; $i++) {
            $this->postJson('/api/chat', ['message' => 'hello'])->assertOk();
        }

        $this->postJson('/api/chat', ['message' => 'hello'])->assertStatus(429);
    }
}
