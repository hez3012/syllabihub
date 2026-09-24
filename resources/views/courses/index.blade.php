@extends('layouts.app')

@section('title', 'Subjects')

@section('header-actions')
    @auth
        @if (auth()->user()->role === 'admin')
            <a href="{{ route('courses.create') }}" class="btn btn-pup-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Add Subject
            </a>
        @endif
    @endauth
@endsection

@section('content')
    {{-- Program tabs + filters --}}
    <div class="sh-filter-bar">
        <div class="sh-filter-tabs">
            <a href="{{ route('courses.index', request()->except(['program', 'page'])) }}"
               class="sh-filter-tab {{ !($filters['program'] ?? null) ? 'active' : '' }}">
                <i class="bi bi-grid-1x2"></i> All
            </a>
            <a href="{{ route('courses.index', array_merge(request()->except(['program', 'page']), ['program' => 'BSIT'])) }}"
               class="sh-filter-tab {{ ($filters['program'] ?? null) === 'BSIT' ? 'active' : '' }}">
                BSIT
            </a>
            <a href="{{ route('courses.index', array_merge(request()->except(['program', 'page']), ['program' => 'DIT'])) }}"
               class="sh-filter-tab {{ ($filters['program'] ?? null) === 'DIT' ? 'active' : '' }}">
                DIT
            </a>
        </div>

        <form method="GET" action="{{ route('courses.index') }}" class="sh-inline-filters">
            @if ($filters['program'] ?? null)
                <input type="hidden" name="program" value="{{ $filters['program'] }}">
            @endif
            <div class="sh-filter-dropdown">
                <i class="bi bi-calendar3"></i>
                <select name="year_level" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Year: All</option>
                    @foreach ([1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) ($filters['year_level'] ?? '') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sh-filter-dropdown">
                <i class="bi bi-clock-history"></i>
                <select name="semester" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Sem: All</option>
                    <option value="1st" @selected(($filters['semester'] ?? null) === '1st')>1st Semester</option>
                    <option value="2nd" @selected(($filters['semester'] ?? null) === '2nd')>2nd Semester</option>
                    <option value="summer" @selected(($filters['semester'] ?? null) === 'summer')>Summer</option>
                </select>
            </div>
            @if (($filters['year_level'] ?? null) || ($filters['semester'] ?? null))
                <a href="{{ route('courses.index', request()->only('program')) }}" class="btn btn-sm btn-pup-outline-dark">
                    <i class="bi bi-x-lg"></i> Clear
                </a>
            @endif
        </form>
    </div>

    {{-- Courses table --}}
    <div class="courses-table-wrap sh-table-colorful">
        <table class="table courses-table align-middle mb-0" id="sh-subjects-table">
            <thead>
                <tr>
                    <th style="width:120px;">Code</th>
                    <th>Title</th>
                    <th style="width:80px;">Units</th>
                    <th style="width:110px;">Year Level</th>
                    <th style="width:110px;">Semester</th>
                    <th style="width:90px;">Syllabus</th>
                    <th style="width:60px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($courses as $course)
                    <tr class="sh-clickable-row" data-course-id="{{ $course->id }}">
                        <td><span class="course-code-tag {{ str_starts_with($course->course_code, 'DIT') ? 'course-code-tag-dit' : '' }}">{{ $course->course_code }}</span></td>
                        <td><span class="sh-table-link">{{ $course->title }}</span></td>
                        <td class="text-muted">{{ $course->credited_units ?? '—' }}</td>
                        <td class="text-muted">{{ $course->yearLevelLabel() }}</td>
                        <td class="text-muted">{{ $course->semesterLabel() }}</td>
                        <td>
                            @if ($course->latestSyllabus)
                                <span class="sh-badge sh-badge-success"><span class="sh-status-dot sh-status-dot-green"></span> Yes</span>
                            @else
                                <span class="sh-badge sh-badge-warning"><span class="sh-status-dot sh-status-dot-amber"></span> Missing</span>
                            @endif
                        </td>
                        <td>
                            <span class="sh-row-view-hint"><i class="bi bi-chevron-right"></i></span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-book"></i>
                                <h3>No subjects found</h3>
                                <p>Try adjusting your filters or add a new subject.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($courses->hasPages())
        <div class="sh-pagination">
            {{ $courses->links() }}
        </div>
    @endif

    {{-- Subject detail slide-in panel --}}
    <div id="sh-panel-backdrop-subject" class="sh-panel-backdrop"></div>
    <div id="sh-subject-panel" class="sh-slide-panel sh-subject-panel">
        <div class="sh-slide-panel-header">
            <span class="sh-panel-breadcrumb" id="sh-panel-breadcrumb">Subjects</span>
            <button type="button" class="sh-panel-close" id="sh-subject-close" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="sh-slide-panel-body" id="sh-panel-content">
            <div class="sh-panel-loading">
                <div class="sh-sage-typing-dot"></div>
                <div class="sh-sage-typing-dot"></div>
                <div class="sh-sage-typing-dot"></div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var table = document.getElementById('sh-subjects-table');
        var panel = document.getElementById('sh-subject-panel');
        var backdrop = document.getElementById('sh-panel-backdrop-subject');
        var closeBtn = document.getElementById('sh-subject-close');
        var panelContent = document.getElementById('sh-panel-content');
        var breadcrumb = document.getElementById('sh-panel-breadcrumb');

        if (!table || !panel) return;

        function openPanel(courseId, courseCode) {
            panel.classList.add('is-open');
            backdrop.classList.add('is-visible');
            backdrop.style.display = 'block';
            document.body.style.overflow = 'hidden';

            breadcrumb.textContent = 'Subjects > ' + courseCode;
            panelContent.innerHTML = '<div class="sh-panel-loading"><div class="sh-sage-typing-dot"></div><div class="sh-sage-typing-dot"></div><div class="sh-sage-typing-dot"></div></div>';

            fetch('/courses/' + courseId + '/panel', {
                headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                panelContent.innerHTML = html;
            })
            .catch(function () {
                panelContent.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-triangle"></i><p>Failed to load subject details.</p></div>';
            });
        }

        function closeSubjectPanel() {
            panel.classList.remove('is-open');
            backdrop.classList.remove('is-visible');
            document.body.style.overflow = '';
            setTimeout(function () { backdrop.style.display = ''; }, 250);
        }

        table.addEventListener('click', function (e) {
            var row = e.target.closest('.sh-clickable-row');
            if (!row) return;
            var courseId = row.dataset.courseId;
            var code = row.querySelector('.course-code-tag')?.textContent || '';
            openPanel(courseId, code);
        });

        closeBtn.addEventListener('click', closeSubjectPanel);
        backdrop.addEventListener('click', closeSubjectPanel);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && panel.classList.contains('is-open')) {
                closeSubjectPanel();
            }
        });
    });
    </script>
    @endpush
@endsection
