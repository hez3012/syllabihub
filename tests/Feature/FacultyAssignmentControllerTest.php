<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FacultyAssignmentControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_view_the_faculty_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'faculty', 'name' => 'Fixture Faculty Member']);

        $response = $this->actingAs($admin)->get('/admin/faculty');

        $response->assertOk();
        $response->assertSee('Fixture Faculty Member');
    }

    public function test_admin_can_assign_a_subject_to_faculty(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create();

        $response = $this->actingAs($admin)->post("/admin/faculty/{$faculty->id}/subjects", [
            'subject_id' => $subject->id,
        ]);

        $response->assertRedirect();
        $this->assertTrue($faculty->subjects()->where('subjects.id', $subject->id)->exists());
    }

    public function test_admin_can_remove_an_assignment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $subject = Subject::factory()->create();
        $faculty->subjects()->attach($subject->id);

        $response = $this->actingAs($admin)->delete("/admin/faculty/{$faculty->id}/subjects/{$subject->id}");

        $response->assertRedirect();
        $this->assertFalse($faculty->subjects()->where('subjects.id', $subject->id)->exists());
    }

    public function test_intern_can_also_manage_assignments(): void
    {
        $intern = User::factory()->create(['role' => 'intern']);
        $faculty = User::factory()->create(['role' => 'faculty']);

        $response = $this->actingAs($intern)->get('/admin/faculty');

        $response->assertOk();
    }

    public function test_targeting_a_non_faculty_user_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $notFaculty = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get("/admin/faculty/{$notFaculty->id}");

        $response->assertNotFound();
    }

    public function test_faculty_role_is_forbidden_from_the_admin_area(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $target = User::factory()->create(['role' => 'faculty']);

        $response = $this->actingAs($faculty)->get("/admin/faculty/{$target->id}");

        $response->assertForbidden();
    }
}
