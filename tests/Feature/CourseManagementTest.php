<?php

namespace Tests\Feature;

use App\Http\Controllers\SyllabusController;
use App\Models\Course;
use App\Models\Program;
use App\Models\Syllabus;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseManagementTest extends TestCase
{
    use DatabaseTransactions;

    private function coursePayload(array $overrides = []): array
    {
        $program = Program::factory()->create();

        return array_merge([
            'program_id' => $program->id,
            'course_code' => 'TST 500',
            'title' => 'Newly Added Fixture Course',
            'year_level' => 2,
            'semester' => '1st',
        ], $overrides);
    }

    public function test_admin_can_create_a_course(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/courses', $this->coursePayload());

        $response->assertRedirect();
        $course = Course::where('course_code', 'TST 500')->firstOrFail();
        $this->assertSame($admin->id, $course->created_by);
    }

    public function test_faculty_can_create_a_course_directly_with_no_approval_needed(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);

        $response = $this->actingAs($faculty)->post('/courses', $this->coursePayload(['course_code' => 'TST 501']));

        $response->assertRedirect();
        $course = Course::where('course_code', 'TST 501')->firstOrFail();
        $this->assertSame($faculty->id, $course->created_by);
        $this->assertFalse($course->hasPendingChangeRequest());
    }

    public function test_intern_can_create_a_course(): void
    {
        $intern = User::factory()->create(['role' => 'intern']);

        $response = $this->actingAs($intern)->post('/courses', $this->coursePayload(['course_code' => 'TST 502']));

        $response->assertRedirect();
        $this->assertDatabaseHas('courses', ['course_code' => 'TST 502', 'created_by' => $intern->id]);
    }

    public function test_guest_cannot_create_a_course(): void
    {
        $response = $this->get('/courses/create');

        $response->assertRedirect(route('login'));
    }

    public function test_duplicate_course_code_within_same_program_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $program = Program::factory()->create();
        Course::factory()->create(['program_id' => $program->id, 'course_code' => 'TST 503']);

        $response = $this->actingAs($admin)->post('/courses', $this->coursePayload([
            'program_id' => $program->id,
            'course_code' => 'TST 503',
        ]));

        $response->assertSessionHasErrors('course_code');
    }

    public function test_duplicate_course_code_across_different_programs_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $programA = Program::factory()->create();
        $programB = Program::factory()->create();
        Course::factory()->create(['program_id' => $programA->id, 'course_code' => 'TST 504']);

        $response = $this->actingAs($admin)->post('/courses', $this->coursePayload([
            'program_id' => $programB->id,
            'course_code' => 'TST 504',
        ]));

        $response->assertSessionHasErrors('course_code');
    }

    public function test_duplicate_title_is_rejected_regardless_of_program(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Course::factory()->create(['title' => 'Shared Fixture Title']);

        $response = $this->actingAs($admin)->post('/courses', $this->coursePayload([
            'course_code' => 'TST 505',
            'title' => 'Shared Fixture Title',
        ]));

        $response->assertSessionHasErrors('title');
    }

    public function test_a_soft_deleted_courses_code_and_title_can_be_reused(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $old = Course::factory()->create(['course_code' => 'TST 506', 'title' => 'Retired Curriculum Course']);
        $old->delete();

        $response = $this->actingAs($admin)->post('/courses', $this->coursePayload([
            'course_code' => 'TST 506',
            'title' => 'Retired Curriculum Course',
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('courses', ['course_code' => 'TST 506', 'deleted_at' => null]);
    }

    public function test_creating_a_course_can_include_an_inline_syllabus_upload(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/courses', array_merge($this->coursePayload(), [
            'file_pdf' => UploadedFile::fake()->create('inline-add.pdf', 10, 'application/pdf'),
            'curriculum_year' => SyllabusController::curriculumYearOptions()[0],
        ]));

        $response->assertRedirect();
        $course = Course::where('course_code', 'TST 500')->firstOrFail();
        $this->assertSame(1, Syllabus::where('course_id', $course->id)->count());
    }

    public function test_creating_a_course_with_an_inline_file_but_no_curriculum_year_is_rejected(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/courses', array_merge($this->coursePayload(), [
            'file_pdf' => UploadedFile::fake()->create('inline-add.pdf', 10, 'application/pdf'),
        ]));

        $response->assertSessionHasErrors('curriculum_year');
        $this->assertDatabaseMissing('courses', ['course_code' => 'TST 500']);
    }

    public function test_editing_a_course_can_replace_its_syllabus_inline(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();
        $original = Syllabus::factory()->create(['course_id' => $course->id, 'file_type' => 'pdf']);

        $response = $this->actingAs($admin)->put("/courses/{$course->id}", array_merge($this->coursePayload([
            'program_id' => $course->program_id,
            'course_code' => $course->course_code,
            'title' => $course->title,
        ]), [
            'file_pdf' => UploadedFile::fake()->create('inline-replace.pdf', 10, 'application/pdf'),
            'curriculum_year' => SyllabusController::curriculumYearOptions()[0],
        ]));

        $response->assertRedirect();
        $this->assertSoftDeleted('syllabi', ['id' => $original->id]);
        $this->assertSame(1, Syllabus::where('course_id', $course->id)->where('file_type', 'pdf')->count());
    }

    public function test_admin_can_edit_any_course_directly(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $course = Course::factory()->create(['created_by' => $faculty->id, 'title' => 'Original Title']);

        $response = $this->actingAs($admin)->put("/courses/{$course->id}", $this->coursePayload([
            'program_id' => $course->program_id,
            'course_code' => $course->course_code,
            'title' => 'Admin-Edited Title',
        ]));

        $response->assertRedirect();
        $this->assertSame('Admin-Edited Title', $course->fresh()->title);
    }

    public function test_admin_can_delete_any_course_directly(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();

        $response = $this->actingAs($admin)->delete("/courses/{$course->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted($course);
    }

    public function test_faculty_cannot_reach_the_direct_edit_route(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $course = Course::factory()->create(['created_by' => $faculty->id]);

        $response = $this->actingAs($faculty)->get("/courses/{$course->id}/edit");

        $response->assertForbidden();
    }

    public function test_faculty_cannot_reach_the_direct_destroy_route(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $course = Course::factory()->create(['created_by' => $faculty->id]);

        $response = $this->actingAs($faculty)->delete("/courses/{$course->id}");

        $response->assertForbidden();
        $this->assertNotSoftDeleted($course);
    }
}
