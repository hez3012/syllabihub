{{-- Change request review panel content (fetched via JS, no layout) --}}
@php
    $fieldLabels = [
        'program_id' => 'Program',
        'course_code' => 'Course code',
        'title' => 'Title',
        'year_level' => 'Year level',
        'semester' => 'Semester',
        'prerequisite' => 'Prerequisite',
        'corequisite' => 'Co-requisite',
        'lecture_hours' => 'Lecture hours',
        'lab_hours' => 'Lab hours',
        'credited_units' => 'Credited units',
        'tuition_hours' => 'Tuition hours',
    ];

    $programs = \App\Models\Program::pluck('name', 'id');
@endphp

<div class="sh-review-header">
    <div class="sh-review-title">
        <span class="course-code-tag {{ str_starts_with($changeRequest->course?->course_code, 'DIT') ? 'course-code-tag-dit' : '' }}">{{ $changeRequest->course?->course_code }}</span>
        <h2>{{ $changeRequest->course?->title }}</h2>
    </div>
    <span class="sh-badge sh-badge-{{ $changeRequest->action === 'delete' ? 'danger' : 'warning' }}">
        {{ ucfirst($changeRequest->action) }} request
    </span>
</div>

<div class="sh-review-meta">
    <div class="sh-meta-row">
        <span class="sh-meta-label">Requested by</span>
        <span class="sh-meta-value">{{ $changeRequest->requester?->name ?? '—' }}</span>
    </div>
    <div class="sh-meta-row">
        <span class="sh-meta-label">Date</span>
        <span class="sh-meta-value">{{ $changeRequest->created_at?->format('M d, Y \a\t g:i A') }}</span>
    </div>
    @if ($changeRequest->status !== 'pending')
        <div class="sh-meta-row">
            <span class="sh-meta-label">Reviewed by</span>
            <span class="sh-meta-value">{{ $changeRequest->reviewer?->name ?? '—' }}</span>
        </div>
        <div class="sh-meta-row">
            <span class="sh-meta-label">Reviewed at</span>
            <span class="sh-meta-value">{{ $changeRequest->reviewed_at?->format('M d, Y \a\t g:i A') }}</span>
        </div>
    @endif
</div>

@if ($changeRequest->action === 'update' && $changeRequest->payload)
    <div class="sh-review-section">
        <h3>Proposed Changes</h3>
        <div class="sh-diff-table">
            <div class="sh-diff-header">
                <span class="sh-diff-col-field">Field</span>
                <span class="sh-diff-col-current">Current</span>
                <span class="sh-diff-col-proposed">Proposed</span>
            </div>
            @foreach ($changeRequest->payload->getArrayCopy() as $field => $newValue)
                @php
                    $currentValue = $changeRequest->course?->{$field};
                    $label = $fieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field));

                    // Resolve program_id to name
                    if ($field === 'program_id') {
                        $currentValue = $changeRequest->course?->program?->code ?? $currentValue;
                        $newValue = \App\Models\Program::find($newValue)?->code ?? $newValue;
                    }
                    // Resolve year_level to label
                    if ($field === 'year_level') {
                        $course = $changeRequest->course;
                        $currentValue = $course ? $course->yearLevelLabel() : $currentValue;
                        $yearLabels = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'];
                        $newValue = $yearLabels[$newValue] ?? $newValue;
                    }
                    // Resolve semester
                    if ($field === 'semester') {
                        $currentValue = ucfirst($currentValue) . ' Semester';
                        $newValue = ucfirst($newValue) . ' Semester';
                    }

                    $changed = "{$currentValue}" !== "{$newValue}";
                @endphp
                <div class="sh-diff-row {{ $changed ? 'sh-diff-changed' : '' }}">
                    <span class="sh-diff-col-field">{{ $label }}</span>
                    <span class="sh-diff-col-current">{{ $currentValue ?: '—' }}</span>
                    <span class="sh-diff-col-proposed">{{ $newValue ?: '—' }}</span>
                </div>
            @endforeach
        </div>
    </div>
@else
    <div class="sh-review-section">
        <div class="sh-review-delete-warning">
            <i class="bi bi-exclamation-triangle"></i>
            <div>
                <strong>Delete Request</strong>
                <p>This course will be permanently removed if approved.</p>
            </div>
        </div>
    </div>
@endif

@if ($changeRequest->review_note)
    <div class="sh-review-section">
        <h3>Rejection Note</h3>
        <p class="sh-review-note">{{ $changeRequest->review_note }}</p>
    </div>
@endif

@if ($changeRequest->status === 'pending')
    <div class="sh-review-actions">
        <form method="POST" action="{{ route('course-requests.approve', $changeRequest) }}" class="sh-review-approve-form">
            @csrf
            <button type="submit" class="btn btn-pup-success">
                <i class="bi bi-check-lg"></i> Approve
            </button>
        </form>

        <div class="sh-review-reject-group">
            <form method="POST" action="{{ route('course-requests.reject', $changeRequest) }}" class="sh-review-reject-form">
                @csrf
                <input type="text" name="review_note" placeholder="Rejection note (optional)" class="form-control form-control-sm" maxlength="255">
                <button type="submit" class="btn btn-pup-danger">
                    <i class="bi bi-x-lg"></i> Reject
                </button>
            </form>
        </div>
    </div>
@endif
