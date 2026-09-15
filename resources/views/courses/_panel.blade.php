{{-- Subject detail slide-in panel content (fetched via JS, no layout) --}}
<div class="sh-panel-detail-header">
    <div class="sh-panel-detail-title">
        <h2>{{ $course->course_code }}</h2>
        <p>{{ $course->title }}</p>
    </div>
    @if ($course->latestSyllabus)
        <a href="{{ route('syllabi.download', $course->latestSyllabus) }}" class="btn btn-pup-primary btn-sm">
            <i class="bi bi-download"></i> Download
        </a>
    @endif
</div>

<div class="sh-panel-detail-meta">
    <div class="sh-meta-row">
        <span class="sh-meta-label">Program</span>
        <span class="sh-meta-value">
            @if ($course->program?->code === 'BSIT')
                <span class="program-pill program-pill-bsit">BSIT</span>
            @elseif ($course->program?->code === 'DIT')
                <span class="program-pill program-pill-dit">DIT</span>
            @else
                {{ $course->program?->code }}
            @endif
        </span>
    </div>
    <div class="sh-meta-row">
        <span class="sh-meta-label">Year level</span>
        <span class="sh-meta-value">{{ $course->yearLevelLabel() }}</span>
    </div>
    <div class="sh-meta-row">
        <span class="sh-meta-label">Semester</span>
        <span class="sh-meta-value">{{ $course->semesterLabel() }}</span>
    </div>
    <div class="sh-meta-row">
        <span class="sh-meta-label">Credits</span>
        <span class="sh-meta-value">{{ $course->credited_units ? $course->credited_units . ' units' : '—' }}</span>
    </div>
    @if ($course->lecture_hours || $course->lab_hours)
        <div class="sh-meta-row">
            <span class="sh-meta-label">Lecture hours</span>
            <span class="sh-meta-value">{{ $course->lecture_hours ? $course->lecture_hours . ' hrs' : '—' }}</span>
        </div>
        <div class="sh-meta-row">
            <span class="sh-meta-label">Lab hours</span>
            <span class="sh-meta-value">{{ $course->lab_hours ? $course->lab_hours . ' hrs' : '—' }}</span>
        </div>
    @endif
    @if ($course->tuition_hours)
        <div class="sh-meta-row">
            <span class="sh-meta-label">Tuition hours</span>
            <span class="sh-meta-value">{{ $course->tuition_hours }} hrs</span>
        </div>
    @endif
    <div class="sh-meta-row">
        <span class="sh-meta-label">Prerequisite</span>
        <span class="sh-meta-value">{{ $course->prerequisite ?: '—' }}</span>
    </div>
    <div class="sh-meta-row">
        <span class="sh-meta-label">Co-requisite</span>
        <span class="sh-meta-value">{{ $course->corequisite ?: '—' }}</span>
    </div>
</div>

<div class="sh-panel-detail-section">
    <h3>Syllabus</h3>

    @forelse ($course->syllabi as $syllabus)
        <div class="sh-panel-syllabus-item">
            <div class="sh-panel-syllabus-info">
                <span class="sh-badge sh-badge-{{ $syllabus->file_type === 'pdf' ? 'info' : 'success' }}">
                    {{ strtoupper($syllabus->file_type) }}
                </span>
                <span class="sh-panel-syllabus-filename">{{ $syllabus->original_filename ?? basename($syllabus->file_path) }}</span>
                @if ($syllabus->curriculum_year)
                    <span class="sh-panel-syllabus-year">{{ $syllabus->curriculum_year }}</span>
                @endif
            </div>
            <div class="sh-panel-syllabus-actions">
                @if ($syllabus->file_type === 'pdf')
                    <a href="{{ route('syllabi.preview', $syllabus) }}" target="_blank" rel="noopener" class="btn btn-sm btn-pup-outline-dark">
                        <i class="bi bi-eye"></i> Preview
                    </a>
                @endif
                <a href="{{ route('syllabi.download', $syllabus) }}" class="btn btn-sm btn-pup-outline-dark">
                    <i class="bi bi-download"></i> Download
                </a>
            </div>
        </div>
    @empty
        <div class="sh-panel-empty-syllabus">
            <i class="bi bi-file-earmark-x"></i>
            <p>No syllabus uploaded yet.</p>
        </div>
    @endforelse
</div>

<div class="sh-panel-detail-section">
    <h3>Actions</h3>
    <div class="sh-panel-actions">
        @auth
            @if (auth()->user()->isAdmin() || auth()->user()->isIntern())
                <a href="{{ route('courses.edit', $course) }}" class="btn btn-pup-outline-dark btn-sm">
                    <i class="bi bi-pencil-square"></i> Edit subject
                </a>
                <form method="POST" action="{{ route('courses.destroy', $course) }}" onsubmit="return confirm('Are you sure you want to delete this course?');" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-pup-danger btn-sm">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </form>
            @elseif (auth()->user()->isFaculty() && $course->created_by === auth()->id())
                @if ($course->hasPendingChangeRequest())
                    <span class="sh-badge sh-badge-warning">A request is already pending</span>
                @else
                    <a href="{{ route('course-requests.edit-form', $course) }}" class="btn btn-pup-outline-dark btn-sm">
                        <i class="bi bi-pencil-square"></i> Request edit
                    </a>
                    <form method="POST" action="{{ route('course-requests.delete', $course) }}" onsubmit="return confirm('Request deletion of this course? An admin will review it first.');" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-pup-danger btn-sm">
                            <i class="bi bi-trash"></i> Request delete
                        </button>
                    </form>
                @endif
            @endif
        @endauth

        @if ($course->latestSyllabus)
            <a href="{{ route('syllabi.create', $course) }}" class="btn btn-pup-outline-dark btn-sm">
                <i class="bi bi-arrow-repeat"></i> Replace syllabus
            </a>
        @else
            <a href="{{ route('syllabi.create', $course) }}" class="btn btn-pup-primary btn-sm">
                <i class="bi bi-upload"></i> Upload syllabus
            </a>
        @endif
    </div>
</div>
