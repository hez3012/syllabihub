<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CourseControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // All routes require auth now (CLAUDE.md §7, 2026-08-12) — every
        // request in this class needs a logged-in user regardless of role.
        $this->actingAs(User::factory()->create(['role' => 'faculty']));
    }

    public function test_index_lists_courses(): void
    {
        $course = Course::factory()->create(['title' => 'Listed Course Fixture']);

        $response = $this->get('/courses');

        $response->assertOk();
        $response->assertSee($course->title);
    }

    public function test_index_filters_by_year_level(): void
    {
        $matching = Course::factory()->create(['year_level' => 3, 'title' => 'Third Year Fixture Course']);
        $other = Course::factory()->create(['year_level' => 1, 'title' => 'First Year Fixture Course']);

        $response = $this->get('/courses?year_level=3');

        $response->assertOk();
        $response->assertSee($matching->title);
        $response->assertDontSee($other->title);
    }

    public function test_index_filters_by_semester(): void
    {
        $matching = Course::factory()->create(['semester' => 'summer', 'title' => 'Summer Fixture Course']);
        $other = Course::factory()->create(['semester' => '1st', 'title' => 'First Sem Fixture Course']);

        $response = $this->get('/courses?semester=summer');

        $response->assertOk();
        $response->assertSee($matching->title);
        $response->assertDontSee($other->title);
    }

    public function test_show_displays_course_detail(): void
    {
        $course = Course::factory()->create(['title' => 'Detail Fixture Course']);

        $response = $this->get("/courses/{$course->id}");

        $response->assertOk();
        $response->assertSee($course->title);
        $response->assertSee($course->course_code);
    }

    public function test_show_returns_404_for_missing_course(): void
    {
        $response = $this->get('/courses/999999');

        $response->assertNotFound();
    }
}
