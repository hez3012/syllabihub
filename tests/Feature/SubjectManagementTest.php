<?php

namespace Tests\Feature;

use App\Http\Controllers\SyllabusController;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Syllabus;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubjectManagementTest extends TestCase
{
    use DatabaseTransactions;

    private function subjectPayload(array $overrides = []): array
    {
        $program = Program::factory()->create();

        return array_merge([
            'program_id' => $program->id,
            'subject_code' => 'TST 500',
            'title' => 'Newly Added Fixture Subject',
            'year_level' => 2,
            'semester' => '1st',
        ], $overrides);
    }

    public function test_admin_can_create_a_subject(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/subjects', $this->subjectPayload());

        $response->assertRedirect();
        $subject = Subject::where('subject_code', 'TST 500')->firstOrFail();
        $this->assertSame($admin->id, $subject->created_by);
    }

    public function test_faculty_can_create_a_subject_directly_with_no_approval_needed(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);

        $response = $this->actingAs($faculty)->post('/subjects', $this->subjectPayload(['subject_code' => 'TST 501']));

        $response->assertRedirect();
        $subject = Subject::where('subject_code', 'TST 501')->firstOrFail();
        $this->assertSame($faculty->id, $subject->created_by);
        $this->assertFalse($subject->hasPendingChangeRequest());
    }

    public function test_intern_can_create_a_subject(): void
    {
        $intern = User::factory()->create(['role' => 'intern']);

        $response = $this->actingAs($intern)->post('/subjects', $this->subjectPayload(['subject_code' => 'TST 502']));

        $response->assertRedirect();
        $this->assertDatabaseHas('subjects', ['subject_code' => 'TST 502', 'created_by' => $intern->id]);
    }

    public function test_guest_cannot_create_a_subject(): void
    {
        $response = $this->get('/subjects/create');

        $response->assertRedirect(route('login'));
    }

    public function test_duplicate_subject_code_within_same_program_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $program = Program::factory()->create();
        Subject::factory()->create(['program_id' => $program->id, 'subject_code' => 'TST 503']);

        $response = $this->actingAs($admin)->post('/subjects', $this->subjectPayload([
            'program_id' => $program->id,
            'subject_code' => 'TST 503',
        ]));

        $response->assertSessionHasErrors('subject_code');
    }

    public function test_duplicate_subject_code_across_different_programs_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $programA = Program::factory()->create();
        $programB = Program::factory()->create();
        Subject::factory()->create(['program_id' => $programA->id, 'subject_code' => 'TST 504']);

        $response = $this->actingAs($admin)->post('/subjects', $this->subjectPayload([
            'program_id' => $programB->id,
            'subject_code' => 'TST 504',
        ]));

        $response->assertSessionHasErrors('subject_code');
    }

    public function test_duplicate_title_is_rejected_regardless_of_program(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Subject::factory()->create(['title' => 'Shared Fixture Title']);

        $response = $this->actingAs($admin)->post('/subjects', $this->subjectPayload([
            'subject_code' => 'TST 505',
            'title' => 'Shared Fixture Title',
        ]));

        $response->assertSessionHasErrors('title');
    }

    public function test_a_soft_deleted_subjects_code_and_title_can_be_reused(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $old = Subject::factory()->create(['subject_code' => 'TST 506', 'title' => 'Retired Curriculum Subject']);
        $old->delete();

        $response = $this->actingAs($admin)->post('/subjects', $this->subjectPayload([
            'subject_code' => 'TST 506',
            'title' => 'Retired Curriculum Subject',
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('subjects', ['subject_code' => 'TST 506', 'deleted_at' => null]);
    }

    public function test_creating_a_subject_can_include_an_inline_syllabus_upload(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/subjects', array_merge($this->subjectPayload(), [
            'file_pdf' => UploadedFile::fake()->create('inline-add.pdf', 10, 'application/pdf'),
            'curriculum_year' => SyllabusController::curriculumYearOptions()[0],
        ]));

        $response->assertRedirect();
        $subject = Subject::where('subject_code', 'TST 500')->firstOrFail();
        $this->assertSame(1, Syllabus::where('subject_id', $subject->id)->count());
    }

    public function test_creating_a_subject_with_an_inline_file_but_no_curriculum_year_is_rejected(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/subjects', array_merge($this->subjectPayload(), [
            'file_pdf' => UploadedFile::fake()->create('inline-add.pdf', 10, 'application/pdf'),
        ]));

        $response->assertSessionHasErrors('curriculum_year');
        $this->assertDatabaseMissing('subjects', ['subject_code' => 'TST 500']);
    }

    public function test_editing_a_subject_can_replace_its_syllabus_inline(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $subject = Subject::factory()->create();
        $original = Syllabus::factory()->create(['subject_id' => $subject->id, 'file_type' => 'pdf']);

        $response = $this->actingAs($admin)->put("/subjects/{$subject->id}", array_merge($this->subjectPayload([
            'program_id' => $subject->program_id,
            'subject_code' => $subject->subject_code,
            'title' => $subject->title,
        ]), [
            'file_pdf' => UploadedFile::fake()->create('inline-replace.pdf', 10, 'application/pdf'),
            'curriculum_year' => SyllabusController::curriculumYearOptions()[0],
        ]));

        $response->assertRedirect();
        $this->assertSoftDeleted('syllabi', ['id' => $original->id]);
        $this->assertSame(1, Syllabus::where('subject_id', $subject->id)->where('file_type', 'pdf')->count());
    }

    public function test_admin_can_edit_any_subject_directly(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['created_by' => $faculty->id, 'title' => 'Original Title']);

        $response = $this->actingAs($admin)->put("/subjects/{$subject->id}", $this->subjectPayload([
            'program_id' => $subject->program_id,
            'subject_code' => $subject->subject_code,
            'title' => 'Admin-Edited Title',
        ]));

        $response->assertRedirect();
        $this->assertSame('Admin-Edited Title', $subject->fresh()->title);
    }

    public function test_admin_can_delete_any_subject_directly(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $subject = Subject::factory()->create();

        $response = $this->actingAs($admin)->delete("/subjects/{$subject->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted($subject);
    }

    public function test_faculty_cannot_reach_the_direct_edit_route(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['created_by' => $faculty->id]);

        $response = $this->actingAs($faculty)->get("/subjects/{$subject->id}/edit");

        $response->assertForbidden();
    }

    public function test_faculty_cannot_reach_the_direct_destroy_route(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['created_by' => $faculty->id]);

        $response = $this->actingAs($faculty)->delete("/subjects/{$subject->id}");

        $response->assertForbidden();
        $this->assertNotSoftDeleted($subject);
    }
}
