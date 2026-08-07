@extends('layouts.guest')

@section('title', 'Login — SyllabiHub')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-5">
            <h1 class="h3 mb-4">Log in</h1>

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
                <div class="mb-3 form-check">
                    <input type="checkbox" name="remember" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                <button type="submit" class="btn btn-primary">Log in</button>
                <a href="{{ route('password.request') }}" class="ms-2">Forgot password?</a>
            </form>
        </div>
    </div>
@endsection
