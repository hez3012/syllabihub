@extends('layouts.app')

@section('title', 'Dashboard')
@section('header-class', 'sh-page-hero')
@section('header-subtitle', 'Track your courses, syllabi, and academic progress.')

@section('content')
    {{-- Stats cards --}}
    <div class="sh-stats-grid">
        @foreach ($programs as $program)
            @php
                $uploaded = $program->with_syllabus_count;
                $total = $program->total_courses;
                $pct = $total > 0 ? round(($uploaded / $total) * 100) : 0;
                $icon = match ($program->code) {
                    'BSIT' => 'bi-display',
                    'DIT' => 'bi-book',
                    default => 'bi-journal-text',
                };
            @endphp
            <div class="sh-stat-card">
                <div class="sh-stat-card-top">
                    <span class="sh-stat-icon"><i class="bi {{ $icon }}"></i></span>
                    <div class="sh-stat-card-headings">
                        <span class="sh-stat-program-code">{{ $program->code }}</span>
                        <div class="sh-stat-card-title">{{ $program->name }}</div>
                    </div>
                    <div class="sh-stat-numbers">
                        <span class="sh-stat-fraction">{{ $uploaded }} / {{ $total }}</span>
                        <span class="sh-stat-fraction-label">courses</span>
                    </div>
                </div>
                <div class="sh-stat-progress">
                    <div class="sh-stat-progress-bar" style="width: {{ $pct }}%"></div>
                </div>
                <div class="sh-stat-card-meta">
                    <span>Uploaded</span>
                    <span class="sh-stat-pct">{{ $pct }}%</span>
                </div>
            </div>
        @endforeach

        @php
            $overallPct = $totalCourses > 0 ? round(($withSyllabus / $totalCourses) * 100) : 0;
        @endphp
        <div class="sh-stat-card">
            <div class="sh-stat-card-top">
                <span class="sh-stat-icon"><i class="bi bi-list-check"></i></span>
                <div class="sh-stat-card-headings">
                    <span class="sh-stat-program-code">ALL</span>
                    <div class="sh-stat-card-title">Overall</div>
                </div>
                <div class="sh-stat-numbers">
                    <span class="sh-stat-fraction">{{ $withSyllabus }} / {{ $totalCourses }}</span>
                    <span class="sh-stat-fraction-label">courses</span>
                </div>
            </div>
            <div class="sh-stat-progress">
                <div class="sh-stat-progress-bar" style="width: {{ $overallPct }}%"></div>
            </div>
            <div class="sh-stat-card-meta">
                <span>Uploaded</span>
                <span class="sh-stat-pct">{{ $overallPct }}%</span>
            </div>
        </div>
    </div>

    {{-- Needs Attention --}}
    <div class="sh-dashboard-section">
        <div class="sh-section-header sh-attention-header">
            <div class="sh-attention-headings">
                <span class="sh-attention-icon"><i class="bi bi-bell-fill"></i></span>
                <div class="sh-attention-heading-text">
                    <h2 class="sh-section-title">Needs Attention</h2>
                    <p class="sh-section-subtitle">These courses do not have uploaded syllabi yet.</p>
                </div>
            </div>
            @if ($missing > 0)
                <span class="sh-badge sh-badge-danger sh-attention-badge"><i class="bi bi-exclamation-triangle-fill"></i>{{ $missing }} missing</span>
            @endif
        </div>

        @if ($missingCourses->isEmpty())
            <div class="sh-empty-state-inline">
                <i class="bi bi-check-circle" style="color: var(--sh-success);"></i>
                <span>All courses have syllabi uploaded.</span>
            </div>
        @else
            <div class="sh-table-wrap">
                <table class="sh-dash-table">
                    <thead>
                        <tr>
                            <th class="sh-dash-num">#</th>
                            <th>Course Code</th>
                            <th>Course Title</th>
                            <th class="sh-dash-center sh-dash-program">Program</th>
                            <th class="sh-dash-center sh-dash-yearsem">Year - Semester</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($missingCourses as $course)
                            <tr>
                                <td class="sh-dash-num">{{ $loop->iteration }}</td>
                                <td class="sh-dash-code"><span class="course-code-tag {{ str_starts_with($course->course_code, 'DIT') ? 'course-code-tag-dit' : '' }}">{{ $course->course_code }}</span></td>
                                <td class="sh-dash-title">{{ $course->title }}</td>
                                <td class="sh-dash-center"><span class="sh-program-badge">{{ $course->programLabel() ?: '—' }}</span></td>
                                <td class="sh-dash-center"><span class="sh-sem-pill">{{ $course->yearLevelLabel() }} · {{ $course->semesterLabel() }} Sem</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($missing > 10)
                <a href="{{ route('courses.index') }}" class="sh-view-all-link">View all {{ $missing }} courses without syllabus <i class="bi bi-arrow-right"></i></a>
            @endif
        @endif
    </div>
@endsection
