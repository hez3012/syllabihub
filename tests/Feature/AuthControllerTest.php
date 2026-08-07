<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login-test@example.com',
            'password' => 'secret123',
            'role' => 'faculty',
        ]);

        $response = $this->post('/login', [
            'email' => 'login-test@example.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('dashboard.redirect'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'wrongpass@example.com',
            'password' => 'correct-password',
        ]);

        $response = $this->post('/login', [
            'email' => 'wrongpass@example.com',
            'password' => 'incorrect-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_dashboard_redirects_admin_to_admin_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('dashboard.admin'));
    }

    public function test_dashboard_redirects_faculty_to_faculty_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'faculty']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('dashboard.faculty'));
    }

    public function test_dashboard_redirects_intern_to_admin_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'intern']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('dashboard.admin'));
    }

    public function test_guest_middleware_redirects_authenticated_user_away_from_login_form(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect(route('dashboard.redirect'));
    }

    public function test_logout_clears_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->assertAuthenticated();

        $response = $this->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
