@extends('layouts.guest')

@section('title', 'Reset Password — SyllabiHub')

@section('content')
    <h1 class="auth-title">Reset password</h1>
    <p class="auth-subtitle">Choose a new password for your account.</p>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email', $email) }}" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label">New password</label>
            <input type="password" name="password" class="form-control" required minlength="8">
        </div>
        <div class="mb-3">
            <label class="form-label">Confirm new password</label>
            <input type="password" name="password_confirmation" class="form-control" required minlength="8">
        </div>
        <button type="submit" class="btn btn-pup-primary w-100">Reset Password</button>
    </form>
@endsection
