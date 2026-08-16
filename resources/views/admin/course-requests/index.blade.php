@extends('layouts.app')

@section('title', 'Course Change Requests — SyllabiHub')

@section('content')
    <a href="{{ route('dashboard.admin') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>

    <div class="page-hero">
        <div class="eyebrow">Admin</div>
        <h1 class="h3 mb-0">Pending Course Change Requests</h1>
    </div>

    @forelse ($requests as $req)
        <div class="request-card">
            <p class="mb-1">
                <span class="badge bg-warning text-dark me-1">{{ ucfirst($req->action) }}</span>
                <span class="course-code-tag me-1">{{ $req->course->course_code }}</span>
                <strong>{{ $req->course->title }}</strong>
            </p>
            <p class="request-meta">Requested by {{ $req->requester->name }} &middot; {{ $req->created_at->format('Y-m-d H:i') }}</p>

            @if ($req->action === 'update')
                @php
                    // Human-readable labels for the raw snake_case payload
                    // keys below — falls back to a humanized version of the
                    // field name itself for anything not listed here.
                    $fieldLabels = [
                        'program_id' => 'Program',
                        'course_code' => 'Course Code',
                        'title' => 'Title',
                        'year_level' => 'Year Level',
                        'semester' => 'Semester',
                        'prerequisite' => 'Prerequisite',
                        'corequisite' => 'Co-requisite',
                        'lecture_hours' => 'Lecture Hours',
                        'lab_hours' => 'Lab Hours',
                        'credited_units' => 'Credited Units',
                        'tuition_hours' => 'Tuition Hours',
                    ];
                @endphp
                <table class="table table-sm table-bordered request-diff-table mb-3">
                    <thead>
                        <tr><th>Field</th><th>Current</th><th>Proposed</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($req->payload->getArrayCopy() as $field => $newValue)
                            <tr>
                                <td>{{ $fieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field)) }}</td>
                                <td>{{ $req->course->{$field} }}</td>
                                <td class="fw-medium">{{ $newValue }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-danger mb-3"><i class="bi bi-exclamation-triangle"></i> Delete request — this course will be removed if approved.</p>
            @endif

            <div class="d-flex gap-2">
                <form method="POST" action="{{ route('course-requests.approve', $req) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-pup-success"><i class="bi bi-check-lg"></i> Approve</button>
                </form>
                <form method="POST" action="{{ route('course-requests.reject', $req) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-pup-danger"><i class="bi bi-x-lg"></i> Reject</button>
                </form>
            </div>
        </div>
    @empty
        <div class="empty-state content-card">
            <i class="bi bi-inbox"></i>
            <p>No pending requests.</p>
        </div>
    @endforelse
@endsection
