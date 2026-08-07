<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Manual password reset — no Breeze/Fortify. Uses Laravel's core Password
 * broker (Illuminate\Support\Facades\Password), which is framework-core,
 * not a scaffolding package — same reasoning as AuthController using
 * Auth::attempt(). Reads/writes the existing password_reset_tokens table
 * (already in the schema, unused until now per CLAUDE.md §6).
 *
 * Mail: MAIL_MAILER=log in .env, so the reset link is written to
 * storage/logs/laravel.log instead of actually being emailed — no SMTP
 * setup needed for local dev/demo. Swap MAIL_MAILER when real delivery is
 * needed; nothing else about this controller has to change.
 */
class PasswordResetController extends Controller
{
    public function showRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::reset(
            $validated,
            function ($user, $password) {
                $user->forceFill(['password' => $password])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }
}
