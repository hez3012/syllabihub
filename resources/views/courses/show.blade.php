@extends('layouts.app')

@section('title', $course->course_code . ' — SyllabiHub')

@section('content')
    <a href="{{ route('courses.index') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Courses</a>

    <div class="sh-section-header">
        <div>
            <div style="display:flex;align-items:center;gap:var(--space-2);margin-bottom:var(--space-1);">
                <span class="course-code-tag">{{ $course->program?->code }}</span>
                <span class="sh-badge sh-badge-muted">{{ $course->yearLevelLabel() }}</span>
            </div>
            <h1 class="sh-section-title">{{ $course->course_code }} — {{ $course->title }}</h1>
        </div>

        @auth
            @if (auth()->user()->isAdmin() || auth()->user()->isIntern())
                <div style="display:flex;gap:var(--space-2);">
                    <a href="{{ route('courses.edit', $course) }}" class="btn btn-pup-warning btn-sm"><i class="bi bi-pencil-square"></i> Edit</a>
                    <form method="POST" action="{{ route('courses.destroy', $course) }}" onsubmit="return confirm('Are you sure you want to delete this course?');" style="margin:0;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-pup-danger btn-sm"><i class="bi bi-trash"></i> Delete</button>
                    </form>
                </div>
            @elseif (auth()->user()->isFaculty() && $course->created_by === auth()->id())
                @if ($course->hasPendingChangeRequest())
                    <span class="sh-badge sh-badge-warning"><i class="bi bi-clock"></i> Request pending</span>
                @else
                    <div style="display:flex;gap:var(--space-2);">
                        <a href="{{ route('course-requests.edit-form', $course) }}" class="btn btn-pup-warning btn-sm"><i class="bi bi-pencil-square"></i> Request Edit</a>
                        <form method="POST" action="{{ route('course-requests.delete', $course) }}" onsubmit="return confirm('Request deletion of this course? An admin will review it first.');" style="margin:0;">
                            @csrf
                            <button type="submit" class="btn btn-pup-danger btn-sm"><i class="bi bi-trash"></i> Request Delete</button>
                        </form>
                    </div>
                @endif
            @endif
        @endauth
    </div>

    <div class="sh-panel-detail-meta">
        <div class="sh-meta-row"><span class="sh-meta-label">Program</span><span class="sh-meta-value">{{ $course->program?->code }}</span></div>
        <div class="sh-meta-row"><span class="sh-meta-label">Year level</span><span class="sh-meta-value">{{ $course->yearLevelLabel() }}</span></div>
        <div class="sh-meta-row"><span class="sh-meta-label">Semester</span><span class="sh-meta-value">{{ $course->semesterLabel() }}</span></div>
        <div class="sh-meta-row"><span class="sh-meta-label">Prerequisite</span><span class="sh-meta-value">{{ $course->prerequisite ?? '—' }}</span></div>
        <div class="sh-meta-row"><span class="sh-meta-label">Co-requisite</span><span class="sh-meta-value">{{ $course->corequisite ?? '—' }}</span></div>
        <div class="sh-meta-row"><span class="sh-meta-label">Lecture hours</span><span class="sh-meta-value">{{ $course->lecture_hours ?? '—' }}</span></div>
        <div class="sh-meta-row"><span class="sh-meta-label">Lab hours</span><span class="sh-meta-value">{{ $course->lab_hours ?? '—' }}</span></div>
        <div class="sh-meta-row"><span class="sh-meta-label">Credited units</span><span class="sh-meta-value">{{ $course->credited_units ?? '—' }}</span></div>
        <div class="sh-meta-row"><span class="sh-meta-label">Added by</span><span class="sh-meta-value">{{ $course->creator?->name ?? 'Seeded/legacy' }}</span></div>
    </div>

    <div class="sh-panel-detail-section">
        <h3>Syllabi</h3>

        @forelse ($course->syllabi as $syllabus)
            <div class="sh-panel-syllabus-item">
                <div class="sh-panel-syllabus-info">
                    <i class="bi bi-file-earmark-text" style="color:var(--sh-red);font-size:1.1rem;"></i>
                    <span class="sh-panel-syllabus-filename">{{ $syllabus->original_filename ?? basename($syllabus->file_path) }}</span>
                    <span class="sh-panel-syllabus-year">{{ $syllabus->curriculum_year ?? 'N/A' }}</span>
                </div>
                <div class="sh-panel-syllabus-actions">
                    @if ($syllabus->file_type === 'pdf')
                        <a href="{{ route('syllabi.preview', $syllabus) }}" target="_blank" rel="noopener" class="btn btn-sm btn-pup-outline-dark">
                            <i class="bi bi-eye"></i> Preview
                        </a>
                    @endif
                    <a href="{{ route('syllabi.download', $syllabus) }}" class="btn btn-sm btn-pup-primary">
                        <i class="bi bi-download"></i> Download
                    </a>
                </div>

            </div>
        @empty
            <div class="sh-panel-empty-syllabus">
                <i class="bi bi-file-earmark-x"></i>
                <p>No syllabus has been uploaded yet.</p>
            </div>
        @endforelse
    </div>

    <div class="sh-panel-actions">
        <a href="{{ route('syllabi.create', $course) }}" class="btn btn-pup-primary">
            <i class="bi bi-upload"></i> Upload Syllabus
        </a>
    </div>
@endsection
