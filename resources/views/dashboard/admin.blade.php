@extends('layouts.app')

@section('title', 'Admin Dashboard — SyllabiHub')

@section('content')
    <h1 class="h3 mb-4">Admin/Intern Dashboard</h1>

    <p>
        <a href="{{ route('faculty-accounts.index') }}" class="btn btn-outline-primary btn-sm">Manage Faculty Accounts</a>
        <a href="{{ route('subject-requests.index') }}" class="btn btn-outline-warning btn-sm">
            Subject Change Requests
            @if ($pendingRequestCount > 0)
                <span class="badge bg-danger">{{ $pendingRequestCount }}</span>
            @endif
        </a>
    </p>

    <h2 class="h5 mt-4">Tracker</h2>
    <div class="row mb-4" style="max-width: 40rem;">
        <div class="col">
            <div class="border rounded p-3 text-center">
                <div class="fs-4">{{ $totalSubjects }}</div>
                <div class="text-muted small">Total subjects</div>
            </div>
        </div>
        <div class="col">
            <div class="border rounded p-3 text-center">
                <div class="fs-4">{{ $withSyllabus }}</div>
                <div class="text-muted small">With syllabus</div>
            </div>
        </div>
        <div class="col">
            <div class="border rounded p-3 text-center">
                <div class="fs-4">{{ $missing }}</div>
                <div class="text-muted small">Missing</div>
            </div>
        </div>
    </div>

    <h2 class="h5">Recent uploads</h2>
    <table class="table table-striped table-bordered align-middle">
        <thead>
            <tr>
                <th>Subject</th>
                <th>Status</th>
                <th>Uploaded by</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recentUploads as $syllabus)
                <tr>
                    <td>{{ $syllabus->subject?->subject_code }}</td>
                    <td>{{ $syllabus->statusLabel() }}</td>
                    <td>{{ $syllabus->uploader?->name ?? '—' }}</td>
                    <td>{{ $syllabus->created_at?->format('Y-m-d H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted">No uploads yet.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
