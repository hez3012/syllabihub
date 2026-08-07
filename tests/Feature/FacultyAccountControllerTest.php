<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FacultyAccountControllerTest extends TestCase
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

    public function test_admin_can_create_a_faculty_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/admin/faculty', [
            'name' => 'New Faculty',
            'email' => 'new-faculty@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('faculty-accounts.index'));

        $faculty = User::where('email', 'new-faculty@example.com')->first();
        $this->assertNotNull($faculty);
        $this->assertSame('faculty', $faculty->role);
    }

    public function test_intern_can_also_create_a_faculty_account(): void
    {
        $intern = User::factory()->create(['role' => 'intern']);

        $response = $this->actingAs($intern)->post('/admin/faculty', [
            'name' => 'Another Faculty',
            'email' => 'another-faculty@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'another-faculty@example.com', 'role' => 'faculty']);
    }

    public function test_password_confirmation_must_match(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/admin/faculty', [
            'name' => 'Mismatch Faculty',
            'email' => 'mismatch-faculty@example.com',
            'password' => 'password123',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'mismatch-faculty@example.com']);
    }

    public function test_faculty_role_is_forbidden_from_the_admin_area(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);

        $response = $this->actingAs($faculty)->get('/admin/faculty');

        $response->assertForbidden();
    }
}
