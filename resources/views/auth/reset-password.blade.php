@extends('layouts.guest')

@section('title', 'Reset Password — SyllabiHub')

@section('content')
    <div class="auth-card">
        <div class="sh-form-section-head">
            <span class="sh-form-section-badge"><i class="bi bi-key"></i></span>
            <span class="sh-form-section-titles">
                <span class="sh-form-section-title">Reset password</span>
                <span class="sh-form-section-hint">Choose a new password for your account.</span>
            </span>
        </div>

        <div class="auth-card-body">
            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="sh-upload-field">
                    <label class="form-label" for="reset-email">Email <span class="sh-label-req">*</span></label>
                    <input type="email" id="reset-email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $email) }}" placeholder="you@example.com" required autofocus>
                    @error('email')
                        <div class="sh-field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="sh-upload-field">
                    <label class="form-label" for="reset-password">New password <span class="sh-label-req">*</span></label>
                    <input type="password" id="reset-password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="At least 8 characters" required minlength="8">
                    @error('password')
                        <div class="sh-field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="sh-upload-field">
                    <label class="form-label" for="reset-password-confirm">Confirm new password <span class="sh-label-req">*</span></label>
                    <input type="password" id="reset-password-confirm" name="password_confirmation" class="form-control" placeholder="Repeat the password" required minlength="8">
                </div>
                <button type="submit" class="btn btn-pup-primary w-100" style="text-align:center; justify-content:center;">Reset Password</button>
            </form>
        </div>
    </div>
@endsection
