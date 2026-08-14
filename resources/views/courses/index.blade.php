@extends('layouts.app')

@section('title', 'Browse Courses — SyllabiHub')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Browse Courses</h1>
        @auth
            @if (auth()->user()->isAdmin() || auth()->user()->isFaculty() || auth()->user()->isIntern())
                <a href="{{ route('courses.create') }}" class="btn btn-primary btn-sm">+ Add Course</a>
            @endif
        @endauth
    </div>

    <form method="GET" action="{{ route('courses.index') }}" class="row g-2 align-items-end mb-4">
        <div class="col-auto">
            <label class="form-label">Program</label>
            <select name="program" class="form-select">
                <option value="">-- Any --</option>
                <option value="BSIT" @selected(($filters['program'] ?? null) === 'BSIT')>BSIT</option>
                <option value="DIT" @selected(($filters['program'] ?? null) === 'DIT')>DIT</option>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label">Year Level</label>
            <select name="year_level" class="form-select">
                <option value="">-- Any --</option>
                @foreach ([1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'] as $value => $label)
                    <option value="{{ $value }}" @selected((string) ($filters['year_level'] ?? '') === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label">Semester</label>
            <select name="semester" class="form-select">
                <option value="">-- Any --</option>
                <option value="1st" @selected(($filters['semester'] ?? null) === '1st')>1st</option>
                <option value="2nd" @selected(($filters['semester'] ?? null) === '2nd')>2nd</option>
                <option value="summer" @selected(($filters['semester'] ?? null) === 'summer')>Summer</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Filter</button>
        </div>
    </form>

    <table class="table table-striped table-bordered align-middle">
        <thead>
            <tr>
                <th>Code</th>
                <th>Title</th>
                <th>Program</th>
                <th>Year</th>
                <th>Semester</th>
                <th>Syllabus?</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($courses as $course)
                <tr>
                    <td>{{ $course->course_code }}</td>
                    <td><a href="{{ route('courses.show', $course) }}">{{ $course->title }}</a></td>
                    <td>{{ $course->program?->code }}</td>
                    <td>{{ $course->yearLevelLabel() }}</td>
                    <td>{{ $course->semesterLabel() }}</td>
                    <td>
                        @if ($course->latestSyllabus)
                            <span class="badge bg-success">Yes</span>
                        @else
                            <span class="badge bg-secondary">No</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted">No matching courses found.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $courses->links() }}
@endsection
