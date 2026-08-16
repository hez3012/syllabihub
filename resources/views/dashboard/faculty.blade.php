@extends('layouts.app')

@section('title', 'Faculty Dashboard — SyllabiHub')

@section('content')
    <div class="page-hero d-flex justify-content-between align-items-end flex-wrap gap-2">
        <div>
            <div class="eyebrow">Faculty</div>
            <h1 class="h3 mb-0">My Courses</h1>
        </div>
        <a href="{{ route('courses.create') }}" class="btn btn-pup-primary btn-sm">+ Add Course</a>
    </div>

    @if ($pendingRequests->isNotEmpty())
        <h2 class="section-heading">Pending Requests</h2>
        <div class="pending-requests-list mb-4">
            @foreach ($pendingRequests as $req)
                <div class="pending-request-item">
                    <span class="badge bg-warning text-dark">{{ ucfirst($req->action) }}</span>
                    <span class="course-code-tag">{{ $req->course->course_code }}</span>
                    <span class="text-muted small">Awaiting admin approval</span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="courses-table-wrap">
        <table class="table courses-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Title</th>
                    <th>Syllabus Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($courses as $course)
                    <tr>
                        <td><span class="course-code-tag">{{ $course->course_code }}</span></td>
                        <td><a href="{{ route('courses.show', $course) }}" class="text-decoration-none fw-medium">{{ $course->title }}</a></td>
                        <td>
                            @if ($course->latestSyllabus)
                                <span class="syllabus-dot syllabus-dot-yes">{{ $course->latestSyllabus->statusLabel() }}</span>
                            @else
                                <span class="syllabus-dot syllabus-dot-no">Not uploaded</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('syllabi.create', $course) }}" class="btn btn-sm btn-pup-outline-dark">Upload/Replace</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">You have not created any courses yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection