@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    {{-- Stats cards --}}
    <div class="sh-stats-grid">
        @foreach ($programs as $program)
            @php
                $uploaded = $program->with_syllabus_count;
                $total = $program->total_courses;
                $pct = $total > 0 ? round(($uploaded / $total) * 100) : 0;
            @endphp
            <div class="sh-stat-card">
                <div class="sh-stat-card-header">
                    <span class="sh-stat-program-code">{{ $program->code }}</span>
                    <span class="sh-stat-fraction">{{ $uploaded }} / {{ $total }}</span>
                </div>
                <div class="sh-stat-card-title">{{ $program->name }}</div>
                <div class="sh-stat-progress">
                    <div class="sh-stat-progress-bar" style="width: {{ $pct }}%"></div>
                </div>
                <div class="sh-stat-card-meta">Uploaded</div>
            </div>
        @endforeach

        @php
            $overallPct = $totalCourses > 0 ? round(($withSyllabus / $totalCourses) * 100) : 0;
        @endphp
        <div class="sh-stat-card sh-stat-card-overall">
            <div class="sh-stat-card-header">
                <span class="sh-stat-program-code">ALL</span>
                <span class="sh-stat-fraction">{{ $withSyllabus }} / {{ $totalCourses }}</span>
            </div>
            <div class="sh-stat-card-title">Overall</div>
            <div class="sh-stat-progress">
                <div class="sh-stat-progress-bar" style="width: {{ $overallPct }}%"></div>
            </div>
            <div class="sh-stat-card-meta">Uploaded</div>
        </div>
    </div>

    {{-- Two-column section: Needs Attention + Pending Requests --}}
    <div class="sh-dashboard-grid">
        {{-- Needs Attention --}}
        <div class="sh-dashboard-section">
            <div class="sh-section-header">
                <h2 class="sh-section-title">Needs Attention</h2>
                @if ($missing > 0)
                    <span class="sh-badge sh-badge-danger">{{ $missing }} missing</span>
                @endif
            </div>

            @if ($missingCourses->isEmpty())
                <div class="sh-empty-state-inline">
                    <i class="bi bi-check-circle" style="color: var(--sh-success);"></i>
                    <span>All courses have syllabi uploaded.</span>
                </div>
            @else
                <div class="sh-attention-list">
                    @foreach ($missingCourses as $course)
                        <a href="{{ route('courses.show', $course) }}" class="sh-attention-item">
                            <div class="sh-attention-item-left">
                                <span class="course-code-tag {{ str_starts_with($course->course_code, 'DIT') ? 'course-code-tag-dit' : '' }}">{{ $course->course_code }}</span>
                                <span class="sh-attention-title">{{ $course->title }}</span>
                            </div>
                            <div class="sh-attention-item-right">
                                <span class="sh-badge sh-badge-outline">{{ $course->program->code }}</span>
                                <span class="sh-attention-semester">{{ $course->yearLevelLabel() }} · {{ $course->semesterLabel() }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
                @if ($missing > 10)
                    <a href="{{ route('courses.index') }}" class="sh-view-all-link">View all {{ $missing }} courses without syllabus <i class="bi bi-arrow-right"></i></a>
                @endif
            @endif
        </div>

        {{-- Pending Requests --}}
        <div class="sh-dashboard-section">
            <div class="sh-section-header">
                <h2 class="sh-section-title">Pending Requests</h2>
                @if ($pendingRequestCount > 0)
                    <a href="{{ route('course-requests.index') }}" class="sh-badge sh-badge-warning">{{ $pendingRequestCount }} pending</a>
                @endif
            </div>

            @if ($pendingRequests->isEmpty())
                <div class="sh-empty-state-branded">
                    <div class="sh-empty-state-icon">
                        <i class="bi bi-inbox"></i>
                    </div>
                    <p class="sh-empty-state-title">No pending requests</p>
                    <p class="sh-empty-state-desc">Faculty submissions will appear here for review.</p>
                </div>
            @else
                <div class="sh-request-list">
                    @foreach ($pendingRequests as $req)
                        <a href="{{ route('course-requests.index') }}" class="sh-request-item">
                            <div class="sh-request-item-left">
                                <span class="course-code-tag {{ str_starts_with($req->course?->course_code, 'DIT') ? 'course-code-tag-dit' : '' }}">{{ $req->course?->course_code }}</span>
                                <span class="sh-request-action">{{ ucfirst($req->action) }} request</span>
                            </div>
                            <div class="sh-request-item-right">
                                <span class="sh-request-by">by {{ $req->requester?->name ?? '—' }}</span>
                                <span class="sh-request-date">{{ $req->created_at?->diffForHumans() }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
                @if ($pendingRequestCount > 10)
                    <a href="{{ route('course-requests.index') }}" class="sh-view-all-link">View all pending requests <i class="bi bi-arrow-right"></i></a>
                @endif
            @endif
        </div>
    </div>

    {{-- Recent Activity --}}
    <div class="sh-dashboard-section sh-dashboard-section-full">
        <div class="sh-section-header">
            <h2 class="sh-section-title">Recent Activity</h2>
        </div>

        @if ($recentUploads->isEmpty())
            <div class="sh-empty-state-inline">
                <i class="bi bi-clock-history"></i>
                <span>No recent uploads yet.</span>
            </div>
        @else
            <div class="sh-activity-list">
                @foreach ($recentUploads as $syllabus)
                    <div class="sh-activity-item">
                        <div class="sh-activity-icon">
                            <i class="bi bi-{{ $syllabus->file_type === 'pdf' ? 'file-earmark-text' : 'file-earmark-word' }}"></i>
                        </div>
                        <div class="sh-activity-content">
                            <div class="sh-activity-text">
                                <span class="sh-activity-action">uploaded</span>
                                <span class="course-code-tag {{ str_starts_with($syllabus->course?->course_code, 'DIT') ? 'course-code-tag-dit' : '' }}">{{ $syllabus->course?->course_code }}</span>
                                <span class="sh-activity-filename">{{ $syllabus->original_filename ?? $syllabus->file_type }}</span>
                            </div>
                            <div class="sh-activity-meta">
                                by {{ $syllabus->uploader?->name ?? '—' }} · {{ $syllabus->created_at?->diffForHumans() }}
                            </div>
                        </div>
                        <span class="sh-badge sh-badge-{{ $syllabus->status === 'processed' ? 'success' : ($syllabus->status === 'failed' ? 'danger' : 'warning') }}">
                            {{ $syllabus->statusLabel() }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
