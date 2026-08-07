@extends('layouts.app')

@section('title', 'Browse Subjects — SyllabiHub')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Browse Subjects</h1>
        @auth
            @if (auth()->user()->isAdmin() || auth()->user()->isFaculty() || auth()->user()->isIntern())
                <a href="{{ route('subjects.create') }}" class="btn btn-primary btn-sm">+ Add Subject</a>
            @endif
        @endauth
    </div>

    <form method="GET" action="{{ route('subjects.index') }}" class="row g-2 align-items-end mb-4">
        <div class="col-auto">
            <label class="form-label">Program</label>
            <select name="program" class="form-select">
                <option value="">-- any --</option>
                <option value="BSIT" @selected(($filters['program'] ?? null) === 'BSIT')>BSIT</option>
                <option value="DIT" @selected(($filters['program'] ?? null) === 'DIT')>DIT</option>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label">Year level</label>
            <input type="number" name="year_level" min="1" max="10" class="form-control" value="{{ $filters['year_level'] ?? '' }}">
        </div>
        <div class="col-auto">
            <label class="form-label">Semester</label>
            <select name="semester" class="form-select">
                <option value="">-- any --</option>
                <option value="1st" @selected(($filters['semester'] ?? null) === '1st')>1st</option>
                <option value="2nd" @selected(($filters['semester'] ?? null) === '2nd')>2nd</option>
                <option value="summer" @selected(($filters['semester'] ?? null) === 'summer')>summer</option>
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
            @forelse ($subjects as $subject)
                <tr>
                    <td>{{ $subject->subject_code }}</td>
                    <td><a href="{{ route('subjects.show', $subject) }}">{{ $subject->title }}</a></td>
                    <td>{{ $subject->program?->code }}</td>
                    <td>{{ $subject->year_level }}</td>
                    <td>{{ $subject->semester }}</td>
                    <td>
                        @if ($subject->latestSyllabus)
                            <span class="badge bg-success">Yes</span>
                        @else
                            <span class="badge bg-secondary">No</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted">Walang subjects na tumugma.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $subjects->links() }}
@endsection
