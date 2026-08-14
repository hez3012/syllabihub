@extends('layouts.app')

@section('title', 'Faculty Dashboard — SyllabiHub')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Courses</h1>
        <a href="{{ route('courses.create') }}" class="btn btn-primary btn-sm">+ Add Course</a>
    </div>

    @if ($pendingRequests->isNotEmpty())
        <h2 class="h6">My pending requests</h2>
        <ul class="list-group mb-4">
            @foreach ($pendingRequests as $req)
                <li class="list-group-item">
                    {{ ucfirst($req->action) }} — {{ $req->course->course_code }} — awaiting admin approval
                </li>
            @endforeach
        </ul>
    @endif

    <table class="table table-striped table-bordered align-middle">
        <thead>
            <tr>
                <th>Code</th>
                <th>Title</th>
                <th>Syllabus status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($courses as $course)
                <tr>
                    <td>{{ $course->course_code }}</td>
                    <td><a href="{{ route('courses.show', $course) }}">{{ $course->title }}</a></td>
                    <td>{{ $course->latestSyllabus?->statusLabel() ?? 'Not uploaded' }}</td>
                    <td><a href="{{ route('syllabi.create', $course) }}" class="btn btn-sm btn-outline-primary">Upload/Replace</a></td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted">You have not created any courses yet.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
