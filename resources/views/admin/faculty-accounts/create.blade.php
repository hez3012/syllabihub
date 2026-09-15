@extends('layouts.app')

@section('title', 'Create Faculty Account')

@section('content')
    <a href="{{ route('faculty-accounts.index') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Faculty Accounts</a>

    <div class="sh-upload-form-header">
        <h1 class="sh-section-title">Create Faculty Account</h1>
        <p class="sh-upload-course-label">Add a new faculty login to the system</p>
    </div>

    <div class="sh-upload-card" style="max-width: 480px;">
        <form method="POST" action="{{ route('faculty-accounts.store') }}">
            @csrf

            <div class="sh-upload-field">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required
                       placeholder="e.g., Maria Santos">
            </div>

            <div class="sh-upload-field">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required
                       placeholder="e.g., msantos@pup.edu.ph">
            </div>

            <div class="sh-upload-field">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required minlength="8"
                       placeholder="Minimum 8 characters">
            </div>

            <div class="sh-upload-field">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="password_confirmation" class="form-control" required minlength="8">
            </div>

            <div class="sh-upload-note">
                <i class="bi bi-info-circle"></i>
                After creating the account, relay the email and password to the faculty member manually, outside the system.
            </div>

            <div class="sh-upload-actions">
                <a href="{{ route('faculty-accounts.index') }}" class="btn btn-pup-outline-dark">Cancel</a>
                <button type="submit" class="btn btn-pup-primary">
                    <i class="bi bi-person-plus"></i> Create Account
                </button>
            </div>
        </form>
    </div>
@endsection
