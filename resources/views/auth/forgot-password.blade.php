@extends('layouts.guest')

@section('title', 'Forgot Password — SyllabiHub')

@section('content')
    <div class="auth-card">
        <div class="sh-form-section-head">
            <span class="sh-form-section-badge"><i class="bi bi-envelope-paper"></i></span>
            <span class="sh-form-section-titles">
                <span class="sh-form-section-title">Forgot your password?</span>
                <span class="sh-form-section-hint">Enter your email address and we'll send you a password reset link.</span>
            </span>
        </div>

        <div class="auth-card-body">
            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="sh-upload-field">
                    <label class="form-label" for="forgot-email">Email <span class="sh-label-req">*</span></label>
                    <input type="email" id="forgot-email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="you@example.com" required autofocus>
                    @error('email')
                        <div class="sh-field-error">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-pup-primary w-100 mb-2" style="text-align:center; justify-content:center;">Send Reset Link</button>
                <a href="{{ route('login') }}" class="auth-link small">&larr; Back to login</a>
            </form>
        </div>
    </div>
@endsection
