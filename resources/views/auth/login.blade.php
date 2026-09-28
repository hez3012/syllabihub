@extends('layouts.guest')

@section('title', 'Admin Login — SyllabiHub')

@section('content')
    <h1 class="auth-title" style="text-align:center; justify-content:center;">Admin Login</h1>
    <p class="auth-subtitle" style="text-align:center; justify-content:center;">Enter your admin credentials to continue.</p>

    <div class="auth-card">
        <div class="auth-card-body">
            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf
                <div class="sh-upload-field">
                    <label class="form-label" for="login-email">Email <span class="sh-label-req">*</span></label>
                    <input type="email" id="login-email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus>
                    @error('email')
                        <div class="sh-field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="sh-upload-field">
                    <label class="form-label" for="login-password">Password <span class="sh-label-req">*</span></label>
                    <input type="password" id="login-password" name="password" class="form-control @error('password') is-invalid @enderror" required>
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
                <button type="submit" class="btn btn-pup-primary w-100" style="text-align:center; justify-content:center;">Log in</button>
            </form>
        </div>
    </div>
@endsection
