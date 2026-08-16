@extends('layouts.guest')

@section('title', 'Login — SyllabiHub')

@section('content')
    <h1 class="auth-title">Log in</h1>
    <p class="auth-subtitle">Enter your faculty account to continue.</p>

    <form method="POST" action="{{ route('login.attempt') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input type="checkbox" name="remember" class="form-check-input" id="remember">
                <label class="form-check-label small" for="remember">Remember me</label>
            </div>
            <a href="{{ route('password.request') }}" class="auth-link small">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-pup-primary w-100">Log in</button>
    </form>
@endsection