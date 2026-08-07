@extends('layouts.app')

@section('title', $subject->subject_code . ' — SyllabiHub')

@section('content')
    <p><a href="{{ route('subjects.index') }}">&larr; Back to Browse</a></p>

    <div class="d-flex justify-content-between align-items-start">
        <h1 class="h3">{{ $subject->subject_code }} — {{ $subject->title }}</h1>

        @auth
            @if (auth()->user()->isAdmin() || auth()->user()->isIntern())
                <div>
                    <a href="{{ route('subjects.edit', $subject) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <form method="POST" action="{{ route('subjects.destroy', $subject) }}" class="d-inline" onsubmit="return confirm('Sigurado ka bang i-delete ang subject na ito?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </div>
            @elseif (auth()->user()->isFaculty() && $subject->created_by === auth()->id())
                @if ($subject->hasPendingChangeRequest())
                    <span class="badge bg-warning text-dark">May naka-pending nang request</span>
                @else
                    <div>
                        <a href="{{ route('subject-requests.edit-form', $subject) }}" class="btn btn-sm btn-outline-secondary">Request edit</a>
                        <form method="POST" action="{{ route('subject-requests.delete', $subject) }}" class="d-inline" onsubmit="return confirm('Mag-request ng delete para sa subject na ito? Mare-review muna ito ng admin.');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger">Request delete</button>
                        </form>
                    </div>
                @endif
            @endif
        @endauth
    </div>

    <ul class="list-group list-group-flush mb-4" style="max-width: 32rem;">
        <li class="list-group-item">Program: {{ $subject->program?->code }}</li>
        <li class="list-group-item">Year level: {{ $subject->year_level }}</li>
        <li class="list-group-item">Semester: {{ $subject->semester }}</li>
        <li class="list-group-item">Prerequisite: {{ $subject->prerequisite ?? '—' }}</li>
        <li class="list-group-item">Co-requisite: {{ $subject->corequisite ?? '—' }}</li>
        <li class="list-group-item">Lecture hours: {{ $subject->lecture_hours ?? '—' }}</li>
        <li class="list-group-item">Lab hours: {{ $subject->lab_hours ?? '—' }}</li>
        <li class="list-group-item">Units: {{ $subject->credited_units ?? '—' }}</li>
        <li class="list-group-item">Added by: {{ $subject->creator?->name ?? 'Seeded/legacy (walang owner)' }}</li>
    </ul>

    <h2 class="h5">Syllabi</h2>
    <ul class="list-group mb-3">
        @forelse ($subject->syllabi as $syllabus)
            <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <span>{{ $syllabus->curriculum_year ?? 'N/A' }} — {{ strtoupper($syllabus->file_type) }} — status: {{ $syllabus->status }}</span>
                    <a href="{{ route('syllabi.download', $syllabus) }}" class="btn btn-sm btn-outline-primary">Download</a>
                </div>

                @if ($syllabus->file_type === 'pdf')
                    <iframe src="{{ route('syllabi.preview', $syllabus) }}" class="w-100 mt-2 border" height="500" title="Preview: {{ $syllabus->curriculum_year }}"></iframe>
                @endif
            </li>
        @empty
            <li class="list-group-item text-muted">Wala pang naka-upload na syllabus.</li>
        @endforelse
    </ul>

    <a href="{{ route('syllabi.create', $subject) }}" class="btn btn-primary">Upload/replace syllabus</a>
    <span class="text-muted ms-2">(kailangan naka-login, admin/faculty/intern)</span>
@endsection
