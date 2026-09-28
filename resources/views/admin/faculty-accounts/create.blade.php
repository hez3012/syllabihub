@extends('layouts.app')

@section('title', 'Create Faculty Account')

@section('content')
    <a href="{{ route('faculty-accounts.index') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Faculty Accounts</a>

    <div class="sh-upload-form-header">
        <h1 class="sh-section-title">Create Faculty Account</h1>
        <p class="sh-upload-course-label">Add a new faculty login to the system</p>
    </div>

    <div class="sh-upload-card" style="max-width: 640px;">
        <form method="POST" action="{{ route('faculty-accounts.store') }}">
            @csrf

            <fieldset class="sh-form-section">
                <legend class="sh-form-section-head">
                    <span class="sh-form-section-badge">1</span>
                    <span class="sh-form-section-titles">
                        <span class="sh-form-section-title">Account Details</span>
                        <span class="sh-form-section-hint">Who this login belongs to.</span>
                    </span>
                </legend>
                <div class="sh-form-section-body">
                    <div class="sh-form-grid sh-form-grid-2">
                        <div class="sh-upload-field">
                            <label class="form-label" for="fa-name">Name <span class="sh-label-req">*</span></label>
                            <input type="text" id="fa-name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required placeholder="e.g., Maria Santos">
                            @error('name')
                                <div class="sh-field-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="sh-upload-field">
                            <label class="form-label" for="fa-email">Email <span class="sh-label-req">*</span></label>
                            <input type="email" id="fa-email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required placeholder="e.g., msantos@pup.edu.ph">
                            @error('email')
                                <div class="sh-field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </fieldset>

            <fieldset class="sh-form-section">
                <legend class="sh-form-section-head">
                    <span class="sh-form-section-badge">2</span>
                    <span class="sh-form-section-titles">
                        <span class="sh-form-section-title">Password</span>
                        <span class="sh-form-section-hint">Minimum of 8 characters.</span>
                    </span>
                </legend>
                <div class="sh-form-section-body">
                    <div class="sh-form-grid sh-form-grid-2">
                        <div class="sh-upload-field">
                            <label class="form-label" for="fa-password">Password <span class="sh-label-req">*</span></label>
                            <input type="password" id="fa-password" name="password" class="form-control @error('password') is-invalid @enderror" required minlength="8" placeholder="Minimum 8 characters">
                            @error('password')
                                <div class="sh-field-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="sh-upload-field">
                            <label class="form-label" for="fa-password-confirm">Confirm Password <span class="sh-label-req">*</span></label>
                            <input type="password" id="fa-password-confirm" name="password_confirmation" class="form-control" required minlength="8" placeholder="Repeat the password">
                        </div>
                    </div>
                </div>
            </fieldset>

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
