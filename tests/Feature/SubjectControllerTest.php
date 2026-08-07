<?php

namespace Tests\Feature;

use App\Models\Subject;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SubjectControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_lists_subjects(): void
    {
        $subject = Subject::factory()->create(['title' => 'Listed Subject Fixture']);

        $response = $this->get('/subjects');

        $response->assertOk();
        $response->assertSee($subject->title);
    }

    public function test_index_filters_by_year_level(): void
    {
        $matching = Subject::factory()->create(['year_level' => 3, 'title' => 'Third Year Fixture Subject']);
        $other = Subject::factory()->create(['year_level' => 1, 'title' => 'First Year Fixture Subject']);

        $response = $this->get('/subjects?year_level=3');

        $response->assertOk();
        $response->assertSee($matching->title);
        $response->assertDontSee($other->title);
    }

    public function test_index_filters_by_semester(): void
    {
        $matching = Subject::factory()->create(['semester' => 'summer', 'title' => 'Summer Fixture Subject']);
        $other = Subject::factory()->create(['semester' => '1st', 'title' => 'First Sem Fixture Subject']);

        $response = $this->get('/subjects?semester=summer');

        $response->assertOk();
        $response->assertSee($matching->title);
        $response->assertDontSee($other->title);
    }

    public function test_show_displays_subject_detail(): void
    {
        $subject = Subject::factory()->create(['title' => 'Detail Fixture Subject']);

        $response = $this->get("/subjects/{$subject->id}");

        $response->assertOk();
        $response->assertSee($subject->title);
        $response->assertSee($subject->subject_code);
    }

    public function test_show_returns_404_for_missing_subject(): void
    {
        $response = $this->get('/subjects/999999');

        $response->assertNotFound();
    }
}
