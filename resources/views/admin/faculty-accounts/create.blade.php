@extends('layouts.app')

@section('title', 'Create Faculty Account — SyllabiHub')

@section('content')
    <p><a href="{{ url()->previous(route('faculty-accounts.index')) }}">&larr; Back</a></p>

    <h1 class="h3 mb-2">Create a Faculty Account</h1>
    <p class="text-muted">Relay the email and password to the faculty member manually, outside the system.</p>

    <form method="POST" action="{{ route('faculty-accounts.store') }}" class="col-md-6">
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
        <button type="submit" class="btn btn-primary">Create account</button>
    </form>
@endsection
