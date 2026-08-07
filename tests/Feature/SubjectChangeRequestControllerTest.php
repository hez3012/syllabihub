<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\SubjectChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SubjectChangeRequestControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function updatePayload(Subject $subject, array $overrides = []): array
    {
        return array_merge([
            'program_id' => $subject->program_id,
            'subject_code' => $subject->subject_code,
            'title' => 'Proposed New Title',
            'year_level' => $subject->year_level,
            'semester' => $subject->semester,
        ], $overrides);
    }

    public function test_faculty_can_submit_an_edit_request_for_their_own_subject(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['created_by' => $faculty->id, 'title' => 'Original Title']);

        $response = $this->actingAs($faculty)->post(
            "/subjects/{$subject->id}/request-update",
            $this->updatePayload($subject)
        );

        $response->assertRedirect(route('subjects.show', $subject));

        $this->assertDatabaseHas('subject_change_requests', [
            'subject_id' => $subject->id,
            'requested_by' => $faculty->id,
            'action' => 'update',
            'status' => 'pending',
        ]);
    }

    public function test_edit_request_does_not_change_the_subject_until_approved(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['created_by' => $faculty->id, 'title' => 'Original Title']);

        $this->actingAs($faculty)->post(
            "/subjects/{$subject->id}/request-update",
            $this->updatePayload($subject, ['title' => 'Should Not Apply Yet'])
        );

        $this->assertSame('Original Title', $subject->fresh()->title);
    }

    public function test_faculty_cannot_request_changes_to_a_subject_they_did_not_create(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $otherFaculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['created_by' => $otherFaculty->id]);

        $response = $this->actingAs($faculty)->post(
            "/subjects/{$subject->id}/request-update",
            $this->updatePayload($subject)
        );

        $response->assertForbidden();
    }

    public function test_faculty_cannot_request_changes_to_a_legacy_subject_with_no_owner(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['created_by' => null]);

        $response = $this->actingAs($faculty)->post("/subjects/{$subject->id}/request-delete");

        $response->assertForbidden();
    }

    public function test_faculty_cannot_submit_a_second_request_while_one_is_pending(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['created_by' => $faculty->id]);

        $this->actingAs($faculty)->post("/subjects/{$subject->id}/request-delete");
        $response = $this->actingAs($faculty)->post(
            "/subjects/{$subject->id}/request-update",
            $this->updatePayload($subject)
        );

        $response->assertStatus(422);
        $this->assertSame(1, SubjectChangeRequest::where('subject_id', $subject->id)->count());
    }

    public function test_faculty_can_submit_a_delete_request_for_their_own_subject(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['created_by' => $faculty->id]);

        $response = $this->actingAs($faculty)->post("/subjects/{$subject->id}/request-delete");

        $response->assertRedirect();
        $this->assertDatabaseHas('subject_change_requests', [
            'subject_id' => $subject->id,
            'action' => 'delete',
            'status' => 'pending',
        ]);
        $this->assertNotSoftDeleted($subject);
    }

    public function test_admin_can_approve_an_update_request_and_it_applies_the_payload(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['created_by' => $faculty->id, 'title' => 'Original Title']);

        $this->actingAs($faculty)->post(
            "/subjects/{$subject->id}/request-update",
            $this->updatePayload($subject, ['title' => 'Approved New Title'])
        );
        $changeRequest = SubjectChangeRequest::where('subject_id', $subject->id)->firstOrFail();

        $response = $this->actingAs($admin)->post("/admin/subject-requests/{$changeRequest->id}/approve");

        $response->assertRedirect();
        $this->assertSame('Approved New Title', $subject->fresh()->title);
        $this->assertSame('approved', $changeRequest->fresh()->status);
        $this->assertSame($admin->id, $changeRequest->fresh()->reviewed_by);
    }

    public function test_admin_can_approve_a_delete_request_and_it_soft_deletes_the_subject(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['created_by' => $faculty->id]);

        $this->actingAs($faculty)->post("/subjects/{$subject->id}/request-delete");
        $changeRequest = SubjectChangeRequest::where('subject_id', $subject->id)->firstOrFail();

        $response = $this->actingAs($admin)->post("/admin/subject-requests/{$changeRequest->id}/approve");

        $response->assertRedirect();
        $this->assertSoftDeleted($subject);
        $this->assertSame('approved', $changeRequest->fresh()->status);
    }

    public function test_admin_can_reject_a_request_and_the_subject_stays_unchanged(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create(['created_by' => $faculty->id, 'title' => 'Untouched Title']);

        $this->actingAs($faculty)->post(
            "/subjects/{$subject->id}/request-update",
            $this->updatePayload($subject, ['title' => 'Rejected Title'])
        );
        $changeRequest = SubjectChangeRequest::where('subject_id', $subject->id)->firstOrFail();

        $response = $this->actingAs($admin)->post("/admin/subject-requests/{$changeRequest->id}/reject", [
            'review_note' => 'Hindi tama ang proposed title.',
        ]);

        $response->assertRedirect();
        $this->assertSame('Untouched Title', $subject->fresh()->title);
        $this->assertSame('rejected', $changeRequest->fresh()->status);
    }

    public function test_faculty_role_is_forbidden_from_the_approval_queue(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);

        $response = $this->actingAs($faculty)->get('/admin/subject-requests');

        $response->assertForbidden();
    }
}
