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

    {{-- Two-column: Needs Attention + Recent Activity --}}
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
                        <a href="{{ route('courses.index') }}" class="sh-attention-item">
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

        {{-- Recent Audit Activity --}}
        <div class="sh-dashboard-section">
            <div class="sh-section-header">
                <h2 class="sh-section-title">Recent Activity</h2>
                <a href="{{ route('audit.index') }}" class="sh-view-all-link">View all <i class="bi bi-arrow-right"></i></a>
            </div>

            @if ($recentActivity->isEmpty())
                <div class="sh-empty-state-inline">
                    <i class="bi bi-clock-history"></i>
                    <span>No recent activity yet.</span>
                </div>
            @else
                <div class="sh-activity-list">
                    @foreach ($recentActivity as $trail)
                        <div class="sh-activity-item">
                            <div class="sh-activity-icon">
                                <i class="bi bi-{{ $trail->action === 'created' ? 'plus-circle' : ($trail->action === 'deleted' ? 'trash' : 'pencil') }}"></i>
                            </div>
                            <div class="sh-activity-content">
                                <div class="sh-activity-text">
                                    <span class="sh-activity-action">{{ $trail->action }}</span>
                                    <span>{{ $trail->description }}</span>
                                </div>
                                <div class="sh-activity-meta">
                                    by {{ $trail->full_name }} · {{ $trail->created_at?->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Recent Uploads --}}
    <div class="sh-dashboard-section sh-dashboard-section-full">
        <div class="sh-section-header">
            <h2 class="sh-section-title">Recent Uploads</h2>
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
