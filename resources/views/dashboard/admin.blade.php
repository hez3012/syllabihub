@extends('layouts.app')

@section('title', 'Admin Dashboard — SyllabiHub')

@section('content')
    <div class="page-hero">
        <div class="eyebrow">Admin / Intern</div>
        <h1 class="h3 mb-0">Dashboard</h1>
    </div>

    <div class="dashboard-actions mb-4">
        <a href="{{ route('faculty-accounts.index') }}" class="btn btn-pup-outline-dark btn-sm">Manage Faculty Accounts</a>
        <a href="{{ route('course-requests.index') }}" class="btn btn-pup-outline-dark btn-sm">
            Course Change Requests
            @if ($pendingRequestCount > 0)
                <span class="badge bg-danger ms-1">{{ $pendingRequestCount }}</span>
            @endif
        </a>
    </div>

    <div class="stat-strip">
        <div class="stat-pill">
            <span class="stat-value">{{ $totalCourses }}</span>
            <span class="stat-label">Total Courses</span>
        </div>
        <div class="stat-pill">
            <span class="stat-value">{{ $withSyllabus }}</span>
            <span class="stat-label">With Syllabus</span>
        </div>
        <div class="stat-pill stat-pill-alert">
            <span class="stat-value">{{ $missing }}</span>
            <span class="stat-label">Missing a Syllabus</span>
        </div>
    </div>

    <h2 class="section-heading">Recent Uploads</h2>
    <div class="courses-table-wrap">
        <table class="table courses-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Status</th>
                    <th>Uploaded By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentUploads as $syllabus)
                    <tr>
                        <td><span class="course-code-tag">{{ $syllabus->course?->course_code }}</span></td>
                        <td>{{ $syllabus->statusLabel() }}</td>
                        <td>{{ $syllabus->uploader?->name ?? '—' }}</td>
                        <td class="text-muted small">{{ $syllabus->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No uploads yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection