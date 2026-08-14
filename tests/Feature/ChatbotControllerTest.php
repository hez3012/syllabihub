<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Syllabus;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
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
 * Gemini (the Groq-rate-limit fallback, 2026-08-13, per Rico — see
 * ChatbotService::tryGeminiFallback()) is deliberately OFF by default
 * here (`services.gemini.key` => null in setUp()), even though the real
 * .env now has a real key configured for it — a real bug this exact
 * setup already caught once: without this, a rate-limit test here made
 * an actual LIVE call to Gemini's API (no Http::fake() pattern was
 * registered for generativelanguage.googleapis.com), burning real quota
 * during an automated test run. Tests that specifically want to exercise
 * the Gemini-fallback path turn it back on locally with a fake key AND
 * a matching Http::fake() entry — see fakeGeminiReply()/
 * fakeGeminiFailure() below.
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
        config(['services.gemini.key' => null]);

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

    private function fakeGeminiReply(string $text): void
    {
        config(['services.gemini.key' => 'fake-gemini-test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['role' => 'model', 'parts' => [['text' => $text]]]],
                ],
            ]),
        ]);
    }

    private function fakeGeminiFailure(): void
    {
        config(['services.gemini.key' => 'fake-gemini-test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'gemini boom'], 500),
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
        $response->assertJsonPath('query_type', 'course_lookup');
        $response->assertJsonPath('answer', 'Meron pong available na syllabus para sa COMP 016 — Web Development.');
        $response->assertJsonFragment(['course_code' => 'COMP 016']);
    }

    public function test_sources_include_course_code_title_and_has_syllabus(): void
    {
        $course = Course::where('course_code', 'COMP 001')->firstOrFail();
        $uploader = User::factory()->create(['role' => 'admin']);
        Syllabus::factory()->create(['course_id' => $course->id, 'file_type' => 'pdf', 'uploaded_by' => $uploader->id]);

        $this->fakeGroqReply('Oo, may PDF na available para sa COMP 001.');

        $response = $this->postJson('/api/chat', ['message' => 'Ano ang COMP 001?']);

        $response->assertOk();
        $source = collect($response->json('sources'))->firstWhere('course_code', 'COMP 001');

        $this->assertNotNull($source);
        $this->assertSame('COMP 001', $source['course_code']);
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
        $this->fakeGroqReply('Hello! How can I help you find a course or syllabus today?');

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
        // English by default (no `language` sent) — see Rico's 2026-08-13
        // reply-language request; the Taglish text this used to say is
        // still available, just no longer the default (see the language
        // preference tests below).
        $response->assertJsonPath('answer', "Sorry, the AI assistant isn't available right now. Here's what I found in the database:");
        $response->assertJsonFragment(['course_code' => 'COMP 016']);
        Http::assertNothingSent();
    }

    public function test_groq_failure_falls_back_to_raw_search_results(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $response = $this->postJson('/api/chat', ['message' => 'Ano ang COMP 016?']);

        $response->assertOk();
        $response->assertJsonPath('answer', "Sorry, the AI assistant isn't available right now. Here's what I found in the database:");
        $response->assertJsonFragment(['course_code' => 'COMP 016']);
    }

    /**
     * Regression/feature test (Rico, 2026-08-13): a real quota hit is a
     * DIFFERENT failure than a generic "AI unavailable" — a user reading
     * the vague generic line has no way to know it's temporary and
     * self-resolving. Body shape here is copied verbatim from an actual
     * Groq 429 response confirmed live the same day. No Gemini key
     * configured (setUp()'s default) — this specifically covers the
     * deterministic-message path when the Groq -> Gemini fallback isn't
     * even available to try, not just when it's available and fails too
     * (see the next test for that one).
     */
    public function test_groq_rate_limit_gets_a_distinct_fallback_message(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'error' => [
                    'message' => 'Rate limit reached for model `llama-3.3-70b-versatile`... on tokens per day (TPD)',
                    'type' => 'tokens',
                    'code' => 'rate_limit_exceeded',
                ],
            ], 429),
        ]);

        $response = $this->postJson('/api/chat', ['message' => 'Ano ang COMP 016?']);

        $response->assertOk();
        $this->assertStringContainsString('daily AI quota', $response->json('answer'));
        $response->assertJsonFragment(['course_code' => 'COMP 016']);
    }

    /**
     * Feature test (Rico, 2026-08-13): Groq's daily quota being hit
     * shouldn't mean Sage goes silent — Gemini (gemini-3.6-flash, see
     * config/services.php) is tried automatically, and its answer is
     * used (with a small note explaining the switch) instead of falling
     * all the way to the generic "here's what I found" line.
     */
    public function test_groq_rate_limit_falls_back_to_gemini_when_configured(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'error' => ['message' => 'Rate limit reached...', 'type' => 'tokens', 'code' => 'rate_limit_exceeded'],
            ], 429),
        ]);
        $this->fakeGeminiReply('COMP 016 is Web Development, a 2nd Year 2nd Sem BSIT course.');

        $response = $this->postJson('/api/chat', ['message' => 'Ano ang COMP 016?']);

        $response->assertOk();
        $answer = $response->json('answer');
        $this->assertStringContainsString('COMP 016 is Web Development', $answer);
        $this->assertStringContainsString('backup AI model', $answer);
        $response->assertJsonFragment(['course_code' => 'COMP 016']);
    }

    /** The fallback for the fallback: Groq rate-limited AND Gemini itself fails — must still degrade to the same deterministic message, not a 500. */
    public function test_groq_rate_limit_falls_back_to_generic_message_when_gemini_also_fails(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'error' => ['message' => 'Rate limit reached...', 'type' => 'tokens', 'code' => 'rate_limit_exceeded'],
            ], 429),
        ]);
        $this->fakeGeminiFailure();

        $response = $this->postJson('/api/chat', ['message' => 'Ano ang COMP 016?']);

        $response->assertOk();
        $this->assertStringContainsString('daily AI quota', $response->json('answer'));
        $response->assertJsonFragment(['course_code' => 'COMP 016']);
    }

    /** Groq's OWN 500/network-style failures must NOT trigger the Gemini fallback — only an actual rate-limit hit should (see ChatbotService's docblock). */
    public function test_non_rate_limit_groq_failure_does_not_call_gemini(): void
    {
        $this->fakeGeminiReply('this should never be reached');
        Http::fake([
            'api.groq.com/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $response = $this->postJson('/api/chat', ['message' => 'Ano ang COMP 016?']);

        $response->assertOk();
        $response->assertJsonPath('answer', "Sorry, the AI assistant isn't available right now. Here's what I found in the database:");
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));
    }

    /**
     * Token-budget conservation (Rico, 2026-08-13): the Groq free tier
     * caps daily TOKEN usage, separate from and tighter than the
     * request-count limiters in AppServiceProvider — max_tokens bounds
     * completion length without shortening a typical answer (Sage's
     * replies are meant to be brief per the prompt's own TONE section).
     */
    public function test_groq_request_includes_a_max_tokens_cap(): void
    {
        $this->fakeGroqReply('...');

        $this->postJson('/api/chat', ['message' => 'hello'])->assertOk();

        Http::assertSent(fn ($request) => ($request['max_tokens'] ?? null) === 600);
    }

    /**
     * Token-budget conservation (Rico, 2026-08-13): only the most recent
     * turns are actually sent to Groq, even if the client sent more (up
     * to the endpoint's own max:20 validation cap) — older turns are
     * dropped from the OUTGOING request only; the client still keeps its
     * own full copy in sessionStorage regardless (see chatbot.js).
     */
    public function test_only_the_most_recent_history_turns_are_sent_to_groq(): void
    {
        $this->fakeGroqReply('...');

        $history = [];
        for ($i = 1; $i <= 16; $i++) {
            $history[] = ['role' => 'user', 'content' => "filler turn {$i}"];
        }
        $history[0] = ['role' => 'user', 'content' => 'THIS-SHOULD-BE-DROPPED'];
        $history[count($history) - 1] = ['role' => 'assistant', 'content' => 'THIS-SHOULD-BE-KEPT'];

        $this->postJson('/api/chat', [
            'message' => 'hello',
            'history' => $history,
        ])->assertOk();

        Http::assertSent(function ($request) {
            $contents = collect($request['messages'])->pluck('content')->implode(' ');

            return !str_contains($contents, 'THIS-SHOULD-BE-DROPPED')
                && str_contains($contents, 'THIS-SHOULD-BE-KEPT');
        });
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
        // English by default (no `language` sent) — see the language
        // preference tests below for the Tagalog/Taglish variants.
        $this->assertStringContainsString('Admin and Intern accounts', $response->json('answer'));
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
     * specific course by its compound code ("COMP 001") was flooding
     * the reply with nearly every BSIT course.
     */
    public function test_asking_about_one_course_does_not_flood_the_whole_program_catalog(): void
    {
        $this->fakeGroqReply('Meron pong available na syllabus para sa COMP 001.');

        $response = $this->postJson('/api/chat', ['message' => 'Can you send me the file of the COMP 001?']);

        $response->assertOk();
        $sources = $response->json('sources');

        $this->assertCount(1, $sources);
        $this->assertSame('COMP 001', $sources[0]['course_code']);
    }

    /**
     * Regression test for a real bug (2026-08-12): a follow-up question
     * that only makes sense together with what was just discussed
     * ("Ilan ang credit units nito?") found nothing on its own.
     */
    public function test_pronoun_follow_up_resolves_to_the_course_named_in_recent_history(): void
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
        $this->assertSame('COMP 001', $sources[0]['course_code']);
    }

    /**
     * Feature test (Rico/supervisor, 2026-08-13): a "Reply language" line
     * is written into what Groq actually receives, defaulting to English
     * when the client doesn't send `language` at all — matches
     * ChatbotController's own default and the widget's own default
     * selection (see chatbot.js).
     */
    public function test_reply_language_defaults_to_english_when_not_sent(): void
    {
        $this->fakeGroqReply('...');

        $this->postJson('/api/chat', ['message' => 'hello'])->assertOk();

        Http::assertSent(function ($request) {
            $text = collect($request['messages'])->last()['content'];

            return str_contains($text, 'Reply language: english');
        });
    }

    /** @return array<string, array{0: string}> */
    public static function languageProvider(): array
    {
        return [
            'tagalog' => ['tagalog'],
            'taglish' => ['taglish'],
        ];
    }

    #[DataProvider('languageProvider')]
    public function test_reply_language_is_forwarded_to_groq_when_explicitly_selected(string $language): void
    {
        $this->fakeGroqReply('...');

        $this->postJson('/api/chat', ['message' => 'hello', 'language' => $language])->assertOk();

        Http::assertSent(function ($request) use ($language) {
            $text = collect($request['messages'])->last()['content'];

            return str_contains($text, "Reply language: {$language}");
        });
    }

    public function test_invalid_language_value_is_rejected(): void
    {
        $response = $this->postJson('/api/chat', ['message' => 'hello', 'language' => 'klingon']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('language');
    }

    /**
     * The deterministic (non-LLM) fallback strings are localized too —
     * Rico, 2026-08-13: "dapat naka-english" applies to every message
     * Sage gives, not just the ones that actually reach Groq.
     */
    public function test_missing_api_key_fallback_respects_tagalog_language_selection(): void
    {
        config(['services.groq.key' => null]);

        $response = $this->postJson('/api/chat', ['message' => 'Ano ang COMP 016?', 'language' => 'tagalog']);

        $response->assertOk();
        $response->assertJsonPath('answer', 'Paumanhin, hindi available ang AI assistant ngayon. Narito ang aking nahanap sa database:');
    }

    /** Same proof as above, for the access-restricted decline. */
    public function test_privileged_decline_respects_tagalog_language_selection(): void
    {
        $response = $this->postJson('/api/chat', [
            'message' => 'How many faculty accounts are existing?',
            'language' => 'tagalog',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Admin at Intern accounts', $response->json('answer'));
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
