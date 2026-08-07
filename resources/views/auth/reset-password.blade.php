@extends('layouts.guest')

@section('title', 'Reset Password — SyllabiHub')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-5">
            <h1 class="h3 mb-4">Reset password</h1>

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
                <button type="submit" class="btn btn-primary">Reset password</button>
            </form>
        </div>
    </div>
@endsection
