@extends('layouts.guest')

@section('title', 'Admin Login — SyllabiHub')

@section('content')
    <h1 class="auth-title">Admin Login</h1>
    <p class="auth-subtitle">Enter your admin credentials to continue.</p>

    <form method="POST" action="{{ route('login.attempt') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus>
            @error('email')
                <div class="sh-field-error">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
            @error('password')
                <div class="sh-field-error">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-4">
            <div class="form-check">
                <input type="checkbox" name="remember" class="form-check-input" id="remember">
                <label class="form-check-label small" for="remember">Remember me</label>
            </div>
        </div>
        <button type="submit" class="btn btn-pup-primary w-100" style="text-align:center; justify-content:center;">Log in.</button>
    </form>
@endsection
