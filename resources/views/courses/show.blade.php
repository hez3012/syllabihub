@extends('layouts.app')

@section('title', $course->course_code . ' — SyllabiHub')

@section('content')
    <a href="{{ route('courses.index') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Courses</a>

    <div class="page-hero d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <div class="eyebrow">{{ $course->program?->code }} &middot; {{ $course->yearLevelLabel() }}</div>
            <h1 class="h3 mb-0">{{ $course->course_code }} — {{ $course->title }}</h1>
        </div>

        @auth
            @if (auth()->user()->isAdmin() || auth()->user()->isIntern())
                <div class="d-flex gap-2">
                    <a href="{{ route('courses.edit', $course) }}" class="btn btn-pup-warning btn-sm"><i class="bi bi-pencil-square"></i> Edit</a>
                    <form method="POST" action="{{ route('courses.destroy', $course) }}" onsubmit="return confirm('Are you sure you want to delete this course?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-pup-danger btn-sm"><i class="bi bi-trash"></i> Delete</button>
                    </form>
                </div>
            @elseif (auth()->user()->isFaculty() && $course->created_by === auth()->id())
                @if ($course->hasPendingChangeRequest())
                    <span class="badge bg-warning text-dark align-self-start">A request is already pending</span>
                @else
                    <div class="d-flex gap-2">
                        <a href="{{ route('course-requests.edit-form', $course) }}" class="btn btn-pup-warning btn-sm"><i class="bi bi-pencil-square"></i> Request Edit</a>
                        <form method="POST" action="{{ route('course-requests.delete', $course) }}" onsubmit="return confirm('Request deletion of this course? An admin will review it first.');">
                            @csrf
                            <button type="submit" class="btn btn-pup-danger btn-sm"><i class="bi bi-trash"></i> Request Delete</button>
                        </form>
                    </div>
                @endif
            @endif
        @endauth
    </div>

    <div class="detail-meta-list">
        <div class="meta-row"><span class="meta-label">Program</span><span>{{ $course->program?->code }}</span></div>
        <div class="meta-row"><span class="meta-label">Year Level</span><span>{{ $course->yearLevelLabel() }}</span></div>
        <div class="meta-row"><span class="meta-label">Semester</span><span>{{ $course->semesterLabel() }}</span></div>
        <div class="meta-row"><span class="meta-label">Prerequisite</span><span>{{ $course->prerequisite ?? '—' }}</span></div>
        <div class="meta-row"><span class="meta-label">Co-requisite</span><span>{{ $course->corequisite ?? '—' }}</span></div>
        <div class="meta-row"><span class="meta-label">Lecture Hours</span><span>{{ $course->lecture_hours ?? '—' }}</span></div>
        <div class="meta-row"><span class="meta-label">Lab Hours</span><span>{{ $course->lab_hours ?? '—' }}</span></div>
        <div class="meta-row"><span class="meta-label">Credited Units</span><span>{{ $course->credited_units ?? '—' }}</span></div>
        <div class="meta-row"><span class="meta-label">Added By</span><span>{{ $course->creator?->name ?? 'Seeded/legacy (no owner)' }}</span></div>
    </div>

    <h2 class="section-heading">Syllabi</h2>

    @forelse ($course->syllabi as $syllabus)
        <div class="syllabus-list-item">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="syllabus-title">{{ $syllabus->original_filename ?? basename($syllabus->file_path) }}</div>
                    <div class="syllabus-meta">{{ $syllabus->curriculum_year ?? 'N/A' }} &middot; {{ $syllabus->statusLabel() }}</div>
                </div>
                <div class="d-flex gap-2">
                    @if ($syllabus->file_type === 'pdf')
                        <button type="button"
                                class="btn btn-sm btn-pup-outline-dark js-syllabus-preview-toggle"
                                data-target="syllabus-preview-{{ $syllabus->id }}"
                                data-src="{{ route('syllabi.preview', $syllabus) }}">
                            <i class="bi bi-eye"></i> <span class="js-syllabus-preview-label">Preview</span>
                        </button>
                        <a href="{{ route('syllabi.preview', $syllabus) }}" target="_blank" rel="noopener" class="btn btn-sm btn-pup-outline-dark">
                            <i class="bi bi-arrows-fullscreen"></i> Open Full Screen
                        </a>
                    @endif
                    <a href="{{ route('syllabi.download', $syllabus) }}" class="btn btn-sm btn-pup-primary">
                        <i class="bi bi-download"></i> Download
                    </a>
                </div>
            </div>

            @if ($syllabus->file_type === 'pdf')
                <iframe id="syllabus-preview-{{ $syllabus->id }}" class="w-100 mt-3 border rounded d-none" height="500" title="Preview: {{ $syllabus->curriculum_year }}"></iframe>
            @endif
        </div>
    @empty
        <div class="empty-state content-card mb-3">
            <i class="bi bi-file-earmark-x"></i>
            <p>No syllabus has been uploaded yet.</p>
        </div>
    @endforelse

    <a href="{{ route('syllabi.create', $course) }}" class="btn btn-pup-primary"><i class="bi bi-upload"></i> Upload/Replace Syllabus</a>
    @guest
        <span class="text-muted ms-2 small">(requires login: admin, faculty, or intern)</span>
    @endguest
@endsection
