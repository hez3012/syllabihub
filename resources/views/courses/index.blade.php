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
            @php
                $yearOptions = ['' => 'Year: All', '1' => '1st Year', '2' => '2nd Year', '3' => '3rd Year', '4' => '4th Year'];
                $semesterOptions = ['' => 'Sem: All', '1st' => '1st Semester', '2nd' => '2nd Semester', 'summer' => 'Summer'];
                $currentYear = (string) ($filters['year_level'] ?? '');
                $currentSemester = (string) ($filters['semester'] ?? '');
            @endphp
            <div class="sh-filter-dropdown">
                <input type="hidden" name="year_level" value="{{ $currentYear }}">
                <button type="button" class="sh-filter-trigger" aria-haspopup="listbox" aria-expanded="false" aria-label="Filter by year level">
                    <span class="sh-filter-value">{{ $yearOptions[$currentYear] ?? 'Year: All' }}</span>
                </button>
                <ul class="sh-filter-menu" role="listbox" aria-label="Filter by year level" hidden>
                    @foreach ($yearOptions as $value => $label)
                        <li class="sh-filter-option" role="option" data-value="{{ $value }}" tabindex="-1"
                            aria-selected="{{ $currentYear === (string) $value ? 'true' : 'false' }}">{{ $label }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="sh-filter-dropdown">
                <input type="hidden" name="semester" value="{{ $currentSemester }}">
                <button type="button" class="sh-filter-trigger" aria-haspopup="listbox" aria-expanded="false" aria-label="Filter by semester">
                    <span class="sh-filter-value">{{ $semesterOptions[$currentSemester] ?? 'Sem: All' }}</span>
                </button>
                <ul class="sh-filter-menu" role="listbox" aria-label="Filter by semester" hidden>
                    @foreach ($semesterOptions as $value => $label)
                        <li class="sh-filter-option" role="option" data-value="{{ $value }}" tabindex="-1"
                            aria-selected="{{ $currentSemester === (string) $value ? 'true' : 'false' }}">{{ $label }}</li>
                    @endforeach
                </ul>
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
            <th style="width:260px;">Program</th>
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
                        <td>
                            <div class="sh-program-chips">
                                @forelse ($course->programs as $program)
                                    <span class="sh-program-chip {{ strtolower($program->code) === 'dit' ? 'sh-program-chip-dit' : 'sh-program-chip-bsit' }}">
                                        <span class="sh-program-chip-code">{{ $program->code }}</span>
                                        <span class="sh-program-chip-meta">{{ $course->pivotYearSemLabel($program) }}</span>
                                    </span>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </div>
                        </td>
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
                        <td colspan="8">
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

        // ---- Custom Year / Semester filter dropdowns (accessible listbox) ----
        var filterDropdowns = Array.prototype.slice.call(document.querySelectorAll('.sh-filter-dropdown'));

        function closeFilterMenus(except) {
            filterDropdowns.forEach(function (dropdown) {
                if (dropdown === except) return;
                var trigger = dropdown.querySelector('.sh-filter-trigger');
                var menu = dropdown.querySelector('.sh-filter-menu');
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
                if (menu) menu.hidden = true;
            });
        }

        filterDropdowns.forEach(function (dropdown) {
            var trigger = dropdown.querySelector('.sh-filter-trigger');
            var menu = dropdown.querySelector('.sh-filter-menu');
            var hidden = dropdown.querySelector('input[type="hidden"]');
            var valueEl = dropdown.querySelector('.sh-filter-value');
            var options = Array.prototype.slice.call(dropdown.querySelectorAll('.sh-filter-option'));
            var form = dropdown.closest('form');
            if (!trigger || !menu || !hidden || !valueEl || !form) return;

            function isOpen() { return trigger.getAttribute('aria-expanded') === 'true'; }

            function openMenu(focusLast) {
                closeFilterMenus(dropdown);
                trigger.setAttribute('aria-expanded', 'true');
                menu.hidden = false;
                var target = options[0];
                options.forEach(function (option) {
                    if (option.getAttribute('aria-selected') === 'true') target = option;
                });
                if (focusLast) target = options[options.length - 1];
                if (target) target.focus();
            }

            function closeMenu(refocus) {
                trigger.setAttribute('aria-expanded', 'false');
                menu.hidden = true;
                if (refocus) trigger.focus();
            }

            function selectOption(option) {
                var value = option.dataset.value;
                var changed = hidden.value !== value;
                hidden.value = value;
                valueEl.textContent = option.textContent.trim();
                options.forEach(function (option_) {
                    option_.setAttribute('aria-selected', option_ === option ? 'true' : 'false');
                });
                closeMenu(true);
                if (changed) form.submit();
            }

            trigger.addEventListener('click', function () {
                if (isOpen()) closeMenu(false); else openMenu(false);
            });

            trigger.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown') { e.preventDefault(); openMenu(false); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); openMenu(true); }
                else if (e.key === 'Escape' && isOpen()) { e.preventDefault(); closeMenu(true); }
            });

            menu.addEventListener('click', function (e) {
                var option = e.target.closest('.sh-filter-option');
                if (option) selectOption(option);
            });

            menu.addEventListener('keydown', function (e) {
                var index = options.indexOf(document.activeElement);

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    options[Math.min(index + 1, options.length - 1)].focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (index <= 0) { trigger.focus(); closeMenu(false); }
                    else options[index - 1].focus();
                } else if (e.key === 'Home') {
                    e.preventDefault();
                    options[0].focus();
                } else if (e.key === 'End') {
                    e.preventDefault();
                    options[options.length - 1].focus();
                } else if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                    e.preventDefault();
                    var active = document.activeElement;
                    if (active && active.classList.contains('sh-filter-option')) selectOption(active);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    closeMenu(true);
                } else if (e.key === 'Tab') {
                    closeMenu(false);
                }
            });

            document.addEventListener('click', function (e) {
                if (!dropdown.contains(e.target)) closeMenu(false);
            });
        });
        // ---- End filter dropdowns ----

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
