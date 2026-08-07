<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_request_form_renders(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertOk();
    }

    public function test_sending_reset_link_for_known_email_dispatches_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset-target@example.com']);

        $response = $this->post('/forgot-password', ['email' => 'reset-target@example.com']);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_sending_reset_link_for_unknown_email_fails_gracefully(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', ['email' => 'nobody-here@example.com']);

        $response->assertSessionHasErrors('email');
        Notification::assertNothingSent();
    }

    public function test_reset_form_renders_with_token_and_email(): void
    {
        $response = $this->get('/reset-password/some-token?email=someone@example.com');

        $response->assertOk();
        $response->assertSee('someone@example.com', false);
    }

    public function test_user_can_reset_password_with_a_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'valid-token@example.com',
            'password' => 'old-password',
        ]);

        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'valid-token@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('brand-new-password', $user->password));
    }

    public function test_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'bad-token@example.com',
            'password' => 'old-password',
        ]);

        $response = $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'bad-token@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertSessionHasErrors('email');

        $user->refresh();
        $this->assertFalse(Hash::check('brand-new-password', $user->password));
    }

    public function test_reset_requires_password_confirmation_to_match(): void
    {
        $user = User::factory()->create(['email' => 'mismatch@example.com']);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'mismatch@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
