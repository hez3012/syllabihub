@extends('layouts.app')

@section('title', $subject->subject_code . ' — SyllabiHub')

@section('content')
    <p><a href="{{ route('subjects.index') }}">&larr; Back</a></p>

    <div class="d-flex justify-content-between align-items-start">
        <h1 class="h3">{{ $subject->subject_code }} — {{ $subject->title }}</h1>

        @auth
            @if (auth()->user()->isAdmin() || auth()->user()->isIntern())
                <div>
                    <a href="{{ route('subjects.edit', $subject) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil-square"></i> Edit</a>
                    <form method="POST" action="{{ route('subjects.destroy', $subject) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this subject?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Delete</button>
                    </form>
                </div>
            @elseif (auth()->user()->isFaculty() && $subject->created_by === auth()->id())
                @if ($subject->hasPendingChangeRequest())
                    <span class="badge bg-warning text-dark">A request is already pending</span>
                @else
                    <div>
                        <a href="{{ route('subject-requests.edit-form', $subject) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil-square"></i> Request Edit</a>
                        <form method="POST" action="{{ route('subject-requests.delete', $subject) }}" class="d-inline" onsubmit="return confirm('Request deletion of this subject? An admin will review it first.');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Request Delete</button>
                        </form>
                    </div>
                @endif
            @endif
        @endauth
    </div>

    <ul class="list-group list-group-flush mb-4" style="max-width: 32rem;">
        <li class="list-group-item">Program: {{ $subject->program?->code }}</li>
        <li class="list-group-item">Year Level: {{ $subject->yearLevelLabel() }}</li>
        <li class="list-group-item">Semester: {{ $subject->semesterLabel() }}</li>
        <li class="list-group-item">Prerequisite: {{ $subject->prerequisite ?? '—' }}</li>
        <li class="list-group-item">Co-requisite: {{ $subject->corequisite ?? '—' }}</li>
        <li class="list-group-item">Lecture Hours: {{ $subject->lecture_hours ?? '—' }}</li>
        <li class="list-group-item">Lab Hours: {{ $subject->lab_hours ?? '—' }}</li>
        <li class="list-group-item">Credited Units: {{ $subject->credited_units ?? '—' }}</li>
        <li class="list-group-item">Added by: {{ $subject->creator?->name ?? 'Seeded/legacy (no owner)' }}</li>
    </ul>

    <h2 class="h5">Syllabi</h2>
    <ul class="list-group mb-3">
        @forelse ($subject->syllabi as $syllabus)
            <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <span>
                        <strong>{{ $syllabus->original_filename ?? basename($syllabus->file_path) }}</strong>
                        — {{ $syllabus->curriculum_year ?? 'N/A' }} — Status: {{ $syllabus->statusLabel() }}
                    </span>
                    <div>
                        @if ($syllabus->file_type === 'pdf')
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary js-syllabus-preview-toggle"
                                    data-target="syllabus-preview-{{ $syllabus->id }}"
                                    data-src="{{ route('syllabi.preview', $syllabus) }}">
                                <i class="bi bi-eye"></i> <span class="js-syllabus-preview-label">Preview</span>
                            </button>
                            <a href="{{ route('syllabi.preview', $syllabus) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrows-fullscreen"></i> Open Full Screen
                            </a>
                        @endif
                        <a href="{{ route('syllabi.download', $syllabus) }}" class="btn btn-sm btn-outline-primary">Download</a>
                    </div>
                </div>

                {{-- No `src` until the Preview button above is clicked (see
                     resources/js/app.js) — the file isn't fetched at all on
                     page load, only on request. --}}
                @if ($syllabus->file_type === 'pdf')
                    <iframe id="syllabus-preview-{{ $syllabus->id }}" class="w-100 mt-2 border d-none" height="500" title="Preview: {{ $syllabus->curriculum_year }}"></iframe>
                @endif
            </li>
        @empty
            <li class="list-group-item text-muted">No syllabus has been uploaded yet.</li>
        @endforelse
    </ul>

    <a href="{{ route('syllabi.create', $subject) }}" class="btn btn-primary">Upload/Replace Syllabus</a>
    <span class="text-muted ms-2">(requires login: admin, faculty, or intern)</span>
@endsection
