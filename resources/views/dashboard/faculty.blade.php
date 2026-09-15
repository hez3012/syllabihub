@extends('layouts.app')

@section('title', 'My Courses')

@section('content')
    {{-- Pending requests banner --}}
    @if ($pendingRequests->isNotEmpty())
        <div class="sh-pending-banner">
            <div class="sh-pending-banner-content">
                <i class="bi bi-clock-history"></i>
                <span>You have {{ $pendingRequests->count() }} pending change request{{ $pendingRequests->count() > 1 ? 's' : '' }} awaiting admin review.</span>
            </div>
        </div>
    @endif

    {{-- Filter tabs --}}
    <div class="sh-filter-bar">
        <div class="sh-filter-tabs" id="faculty-filter-tabs">
            <button type="button" class="sh-filter-tab active" data-filter="all">All</button>
            <button type="button" class="sh-filter-tab" data-filter="has-syllabus">Has Syllabus</button>
            <button type="button" class="sh-filter-tab" data-filter="missing-syllabus">Missing Syllabus</button>
        </div>
    </div>

    {{-- Courses table --}}
    <div class="courses-table-wrap">
        <table class="table courses-table align-middle mb-0" id="faculty-courses-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Title</th>
                    <th>Semester</th>
                    <th>Syllabus</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($courses as $course)
                    <tr data-has-syllabus="{{ $course->latestSyllabus ? 'yes' : 'no' }}">
                        <td><span class="course-code-tag {{ str_starts_with($course->course_code, 'DIT') ? 'course-code-tag-dit' : '' }}">{{ $course->course_code }}</span></td>
                        <td>
                            <a href="{{ route('courses.show', $course) }}" class="sh-table-link">{{ $course->title }}</a>
                        </td>
                        <td class="text-muted">{{ $course->yearLevelLabel() }} {{ $course->semesterLabel() }}</td>
                        <td>
                            @if ($course->latestSyllabus)
                                <span class="sh-badge sh-badge-success">
                                    <span class="sh-status-dot sh-status-dot-green"></span> {{ strtoupper($course->latestSyllabus->file_type) }}
                                </span>
                            @else
                                <span class="sh-badge sh-badge-warning"><span class="sh-status-dot sh-status-dot-amber"></span> Missing</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($course->latestSyllabus)
                                <a href="{{ route('syllabi.download', $course->latestSyllabus) }}" class="btn btn-sm btn-pup-outline-dark" title="Download syllabus">
                                    <i class="bi bi-download"></i>
                                </a>
                            @else
                                <a href="{{ route('syllabi.create', $course) }}" class="btn btn-sm btn-pup-primary" title="Upload syllabus">
                                    <i class="bi bi-upload"></i> Upload
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="bi bi-book"></i>
                                <h3>No courses yet</h3>
                                <p>You haven't created any courses yet.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pending requests detail --}}
    @if ($pendingRequests->isNotEmpty())
        <div class="sh-dashboard-section sh-dashboard-section-full" style="margin-top: var(--space-6);">
            <div class="sh-section-header">
                <h2 class="sh-section-title">My Pending Requests</h2>
            </div>
            <div class="sh-request-list">
                @foreach ($pendingRequests as $req)
                    <div class="sh-request-item sh-request-item-static">
                        <div class="sh-request-item-left">
                            <span class="course-code-tag {{ str_starts_with($req->course?->course_code, 'DIT') ? 'course-code-tag-dit' : '' }}">{{ $req->course?->course_code }}</span>
                            <span class="sh-request-action">{{ ucfirst($req->action) }} request</span>
                        </div>
                        <div class="sh-request-item-right">
                            <span class="sh-badge sh-badge-warning">Pending review</span>
                            <span class="sh-request-date">{{ $req->created_at?->diffForHumans() }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const tabs = document.querySelectorAll('#faculty-filter-tabs .sh-filter-tab');
        const rows = document.querySelectorAll('#faculty-courses-table tbody tr[data-has-syllabus]');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) { t.classList.remove('active'); });
                tab.classList.add('active');

                const filter = tab.dataset.filter;

                rows.forEach(function (row) {
                    if (filter === 'all') {
                        row.style.display = '';
                    } else if (filter === 'has-syllabus') {
                        row.style.display = row.dataset.hasSyllabus === 'yes' ? '' : 'none';
                    } else if (filter === 'missing-syllabus') {
                        row.style.display = row.dataset.hasSyllabus === 'no' ? '' : 'none';
                    }
                });
            });
        });
    });
    </script>
    @endpush
@endsection
