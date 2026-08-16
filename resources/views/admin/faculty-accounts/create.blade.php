@extends('layouts.app')

@section('title', 'Create Faculty Account — SyllabiHub')

@section('content')
    <a href="{{ route('faculty-accounts.index') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Faculty Accounts</a>

    <div class="page-hero">
        <div class="eyebrow">Admin</div>
        <h1 class="h3 mb-0">Create a Faculty Account</h1>
    </div>

    <div class="content-card" style="max-width: 32rem;">
        <p class="form-hint">Relay the email and password to the faculty member manually, outside the system.</p>

        <form method="POST" action="{{ route('faculty-accounts.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required minlength="8">
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm password</label>
                <input type="password" name="password_confirmation" class="form-control" required minlength="8">
            </div>
            <button type="submit" class="btn btn-pup-primary mt-1"><i class="bi bi-person-plus"></i> Create Account</button>
        </form>
    </div>
@endsection
