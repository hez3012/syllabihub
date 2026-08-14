<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CourseChangeRequestControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function updatePayload(Course $course, array $overrides = []): array
    {
        return array_merge([
            'program_id' => $course->program_id,
            'course_code' => $course->course_code,
            'title' => 'Proposed New Title',
            'year_level' => $course->year_level,
            'semester' => $course->semester,
        ], $overrides);
    }

    public function test_faculty_can_submit_an_edit_request_for_their_own_course(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $course = Course::factory()->create(['created_by' => $faculty->id, 'title' => 'Original Title']);

        $response = $this->actingAs($faculty)->post(
            "/courses/{$course->id}/request-update",
            $this->updatePayload($course)
        );

        $response->assertRedirect(route('courses.show', $course));

        $this->assertDatabaseHas('course_change_requests', [
            'course_id' => $course->id,
            'requested_by' => $faculty->id,
            'action' => 'update',
            'status' => 'pending',
        ]);
    }

    public function test_edit_request_does_not_change_the_course_until_approved(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $course = Course::factory()->create(['created_by' => $faculty->id, 'title' => 'Original Title']);

        $this->actingAs($faculty)->post(
            "/courses/{$course->id}/request-update",
            $this->updatePayload($course, ['title' => 'Should Not Apply Yet'])
        );

        $this->assertSame('Original Title', $course->fresh()->title);
    }

    public function test_submitting_an_edit_request_with_no_actual_changes_is_rejected(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $course = Course::factory()->create(['created_by' => $faculty->id]);

        $response = $this->actingAs($faculty)->post("/courses/{$course->id}/request-update", [
            'program_id' => $course->program_id,
            'course_code' => $course->course_code,
            'title' => $course->title,
            'year_level' => $course->year_level,
            'semester' => $course->semester,
            'prerequisite' => $course->prerequisite,
            'corequisite' => $course->corequisite,
            'lecture_hours' => $course->lecture_hours,
            'lab_hours' => $course->lab_hours,
            'credited_units' => $course->credited_units,
            'tuition_hours' => $course->tuition_hours,
        ]);

        $response->assertSessionHasErrors('request');
        $this->assertDatabaseMissing('course_change_requests', ['course_id' => $course->id]);
    }

    public function test_faculty_cannot_request_changes_to_a_course_they_did_not_create(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $otherFaculty = User::factory()->create(['role' => 'faculty']);
        $course = Course::factory()->create(['created_by' => $otherFaculty->id]);

        $response = $this->actingAs($faculty)->post(
            "/courses/{$course->id}/request-update",
            $this->updatePayload($course)
        );

        $response->assertForbidden();
    }

    public function test_faculty_cannot_request_changes_to_a_legacy_course_with_no_owner(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $course = Course::factory()->create(['created_by' => null]);

        $response = $this->actingAs($faculty)->post("/courses/{$course->id}/request-delete");

        $response->assertForbidden();
    }

    public function test_faculty_cannot_submit_a_second_request_while_one_is_pending(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $course = Course::factory()->create(['created_by' => $faculty->id]);

        $this->actingAs($faculty)->post("/courses/{$course->id}/request-delete");
        $response = $this->actingAs($faculty)->post(
            "/courses/{$course->id}/request-update",
            $this->updatePayload($course)
        );

        $response->assertStatus(422);
        $this->assertSame(1, CourseChangeRequest::where('course_id', $course->id)->count());
    }

    public function test_faculty_can_submit_a_delete_request_for_their_own_course(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $course = Course::factory()->create(['created_by' => $faculty->id]);

        $response = $this->actingAs($faculty)->post("/courses/{$course->id}/request-delete");

        $response->assertRedirect();
        $this->assertDatabaseHas('course_change_requests', [
            'course_id' => $course->id,
            'action' => 'delete',
            'status' => 'pending',
        ]);
        $this->assertNotSoftDeleted($course);
    }

    public function test_admin_can_approve_an_update_request_and_it_applies_the_payload(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $course = Course::factory()->create(['created_by' => $faculty->id, 'title' => 'Original Title']);

        $this->actingAs($faculty)->post(
            "/courses/{$course->id}/request-update",
            $this->updatePayload($course, ['title' => 'Approved New Title'])
        );
        $changeRequest = CourseChangeRequest::where('course_id', $course->id)->firstOrFail();

        $response = $this->actingAs($admin)->post("/admin/course-requests/{$changeRequest->id}/approve");

        $response->assertRedirect();
        $this->assertSame('Approved New Title', $course->fresh()->title);
        $this->assertSame('approved', $changeRequest->fresh()->status);
        $this->assertSame($admin->id, $changeRequest->fresh()->reviewed_by);
    }

    public function test_admin_can_approve_a_delete_request_and_it_soft_deletes_the_course(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $course = Course::factory()->create(['created_by' => $faculty->id]);

        $this->actingAs($faculty)->post("/courses/{$course->id}/request-delete");
        $changeRequest = CourseChangeRequest::where('course_id', $course->id)->firstOrFail();

        $response = $this->actingAs($admin)->post("/admin/course-requests/{$changeRequest->id}/approve");

        $response->assertRedirect();
        $this->assertSoftDeleted($course);
        $this->assertSame('approved', $changeRequest->fresh()->status);
    }

    public function test_admin_can_reject_a_request_and_the_course_stays_unchanged(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $course = Course::factory()->create(['created_by' => $faculty->id, 'title' => 'Untouched Title']);

        $this->actingAs($faculty)->post(
            "/courses/{$course->id}/request-update",
            $this->updatePayload($course, ['title' => 'Rejected Title'])
        );
        $changeRequest = CourseChangeRequest::where('course_id', $course->id)->firstOrFail();

        $response = $this->actingAs($admin)->post("/admin/course-requests/{$changeRequest->id}/reject", [
            'review_note' => 'Hindi tama ang proposed title.',
        ]);

        $response->assertRedirect();
        $this->assertSame('Untouched Title', $course->fresh()->title);
        $this->assertSame('rejected', $changeRequest->fresh()->status);
    }

    public function test_faculty_role_is_forbidden_from_the_approval_queue(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);

        $response = $this->actingAs($faculty)->get('/admin/course-requests');

        $response->assertForbidden();
    }
}
