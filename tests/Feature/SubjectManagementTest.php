<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
