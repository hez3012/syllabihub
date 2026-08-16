@extends('layouts.app')

@section('title', 'Browse Courses — SyllabiHub')

@section('content')
    <div class="page-hero d-flex justify-content-between align-items-end flex-wrap gap-2">
        <div>
            <div class="eyebrow">BSIT &amp; DIT Course Catalog</div>
            <h1 class="h3 mb-0">Browse Courses</h1>
        </div>
        @auth
            @if (auth()->user()->isAdmin() || auth()->user()->isFaculty() || auth()->user()->isIntern())
                <a href="{{ route('courses.create') }}" class="btn btn-pup-primary btn-sm">+ Add Course</a>
            @endif
        @endauth
    </div>

    <div class="stat-strip">
    <div class="stat-pill">
        <span class="stat-value">{{ $courses->total() }}</span>
        <span class="stat-label">Total Courses</span>
    </div>
    <div class="stat-pill">
        <span class="stat-value">{{ $withSyllabusCount }}</span>
        <span class="stat-label">With Syllabus</span>
    </div>
    </div>

    <div class="courses-toolbar">
        <label class="form-label d-block">Program</label>
        <div class="program-toggle mb-3">
            <a href="{{ route('courses.index', request()->except(['program', 'page'])) }}"
               class="program-toggle-btn {{ !($filters['program'] ?? null) ? 'active' : '' }}">All</a>
            <a href="{{ route('courses.index', array_merge(request()->except(['program', 'page']), ['program' => 'BSIT'])) }}"
               class="program-toggle-btn program-toggle-bsit {{ ($filters['program'] ?? null) === 'BSIT' ? 'active' : '' }}">BSIT</a>
            <a href="{{ route('courses.index', array_merge(request()->except(['program', 'page']), ['program' => 'DIT'])) }}"
               class="program-toggle-btn program-toggle-dit {{ ($filters['program'] ?? null) === 'DIT' ? 'active' : '' }}">DIT</a>
        </div>

        <form method="GET" action="{{ route('courses.index') }}" class="row g-3 align-items-end">
            @if ($filters['program'] ?? null)
                <input type="hidden" name="program" value="{{ $filters['program'] }}">
            @endif
            <div class="col-auto">
                <label class="form-label">Year Level</label>
                <select name="year_level" class="form-select">
                    <option value="">All Years</option>
                    @foreach ([1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) ($filters['year_level'] ?? '') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">Semester</label>
                <select name="semester" class="form-select">
                    <option value="">All Semesters</option>
                    <option value="1st" @selected(($filters['semester'] ?? null) === '1st')>1st</option>
                    <option value="2nd" @selected(($filters['semester'] ?? null) === '2nd')>2nd</option>
                    <option value="summer" @selected(($filters['semester'] ?? null) === 'summer')>Summer</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-pup-primary">Filter</button>
            </div>
        </form>
    </div>

    <div class="courses-table-wrap">
        <table class="table courses-table align-middle mb-0">
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
                        <td><span class="course-code-tag">{{ $course->course_code }}</span></td>
                        <td><a href="{{ route('courses.show', $course) }}" class="text-decoration-none fw-medium">{{ $course->title }}</a></td>
                        <td>
                            @if ($course->program?->code === 'BSIT')
                                <span class="program-pill program-pill-bsit">BSIT</span>
                            @elseif ($course->program?->code === 'DIT')
                                <span class="program-pill program-pill-dit">DIT</span>
                            @else
                                {{ $course->program?->code }}
                            @endif
                        </td>
                        <td>{{ $course->yearLevelLabel() }}</td>
                        <td>{{ $course->semesterLabel() }}</td>
                        <td>
                            @if ($course->latestSyllabus)
                                <span class="syllabus-dot syllabus-dot-yes">Available</span>
                            @else
                                <span class="syllabus-dot syllabus-dot-no">None</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No matching courses found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $courses->links() }}
    </div>
@endsection