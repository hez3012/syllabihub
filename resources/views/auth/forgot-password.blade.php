@extends('layouts.guest')

@section('title', 'Forgot Password — SyllabiHub')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-5">
            <h1 class="h3 mb-3">Forgot your password?</h1>
            <p class="text-muted">Enter your email address and we will send you a password reset link.</p>

            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary">Send reset link</button>
                <a href="{{ route('login') }}" class="ms-2">Back to login</a>
            </form>
        </div>
    </div>
@endsection
