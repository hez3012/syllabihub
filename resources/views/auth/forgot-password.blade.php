@extends('layouts.guest')

@section('title', 'Forgot Password — SyllabiHub')

@section('content')
    <h1 class="auth-title">Forgot your password?</h1>
    <p class="auth-subtitle">Enter your email address and we'll send you a password reset link.</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus>
            @error('email')
                <div class="sh-field-error">{{ $message }}</div>
            @enderror
        </div>
        <button type="submit" class="btn btn-pup-primary w-100 mb-2">Send Reset Link</button>
        <a href="{{ route('login') }}" class="auth-link small">&larr; Back to login</a>
    </form>
@endsection
