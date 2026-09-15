@extends('layouts.app')

@section('title', 'Change Requests')

@section('content')
    {{-- Status tabs --}}
    <div class="sh-filter-bar">
        <div class="sh-filter-tabs">
            <a href="{{ route('course-requests.index', ['status' => 'pending']) }}"
               class="sh-filter-tab {{ $currentStatus === 'pending' ? 'active' : '' }}">
                Pending
                @if ($pendingCount > 0)
                    <span class="sh-badge sh-badge-danger" style="margin-left: 6px;">{{ $pendingCount }}</span>
                @endif
            </a>
            <a href="{{ route('course-requests.index', ['status' => 'approved']) }}"
               class="sh-filter-tab {{ $currentStatus === 'approved' ? 'active' : '' }}">Approved</a>
            <a href="{{ route('course-requests.index', ['status' => 'rejected']) }}"
               class="sh-filter-tab {{ $currentStatus === 'rejected' ? 'active' : '' }}">Rejected</a>
        </div>
    </div>

    {{-- Requests table --}}
    <div class="courses-table-wrap">
        <table class="table courses-table align-middle mb-0" id="sh-requests-table">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Requested by</th>
                    <th>Action</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $req)
                    <tr class="sh-clickable-row" data-request-id="{{ $req->id }}">
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span class="course-code-tag {{ str_starts_with($req->course?->course_code, 'DIT') ? 'course-code-tag-dit' : '' }}">{{ $req->course?->course_code }}</span>
                                <span class="sh-table-link">{{ $req->course?->title }}</span>
                            </div>
                        </td>
                        <td class="text-muted">{{ $req->requester?->name ?? '—' }}</td>
                        <td>
                            <span class="sh-badge sh-badge-{{ $req->action === 'delete' ? 'danger' : 'warning' }}">
                                {{ ucfirst($req->action) }}
                            </span>
                        </td>
                        <td class="text-muted">{{ $req->created_at?->diffForHumans() }}</td>
                        <td class="text-end">
                            @if ($currentStatus === 'pending')
                                <button type="button" class="btn btn-sm btn-pup-outline-dark sh-review-btn" data-request-id="{{ $req->id }}">
                                    <i class="bi bi-eye"></i> Review
                                </button>
                            @else
                                <span class="sh-badge sh-badge-{{ $req->status === 'approved' ? 'success' : 'danger' }}">
                                    {{ ucfirst($req->status) }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <h3>No {{ $currentStatus }} requests</h3>
                                <p>There are no {{ $currentStatus }} change requests at this time.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Review slide-in panel --}}
    <div id="sh-panel-backdrop-review" class="sh-panel-backdrop"></div>
    <div id="sh-review-panel" class="sh-slide-panel sh-review-panel">
        <div class="sh-slide-panel-header">
            <span class="sh-panel-breadcrumb" id="sh-review-breadcrumb">Change Requests</span>
            <button type="button" class="sh-panel-close" id="sh-review-close" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="sh-slide-panel-body" id="sh-review-content">
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
        var table = document.getElementById('sh-requests-table');
        var panel = document.getElementById('sh-review-panel');
        var backdrop = document.getElementById('sh-panel-backdrop-review');
        var closeBtn = document.getElementById('sh-review-close');
        var panelContent = document.getElementById('sh-review-content');
        var breadcrumb = document.getElementById('sh-review-breadcrumb');

        if (!table || !panel) return;

        function openPanel(requestId) {
            panel.classList.add('is-open');
            backdrop.classList.add('is-visible');
            backdrop.style.display = 'block';
            document.body.style.overflow = 'hidden';

            breadcrumb.textContent = 'Change Requests > Review';
            panelContent.innerHTML = '<div class="sh-panel-loading"><div class="sh-sage-typing-dot"></div><div class="sh-sage-typing-dot"></div><div class="sh-sage-typing-dot"></div></div>';

            fetch('/admin/course-requests/' + requestId + '/panel', {
                headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                panelContent.innerHTML = html;
            })
            .catch(function () {
                panelContent.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-triangle"></i><p>Failed to load request details.</p></div>';
            });
        }

        function closeReviewPanel() {
            panel.classList.remove('is-open');
            backdrop.classList.remove('is-visible');
            document.body.style.overflow = '';
            setTimeout(function () { backdrop.style.display = ''; }, 250);
        }

        // Clickable rows
        table.addEventListener('click', function (e) {
            var reviewBtn = e.target.closest('.sh-review-btn');
            if (reviewBtn) {
                openPanel(reviewBtn.dataset.requestId);
                return;
            }
            var row = e.target.closest('.sh-clickable-row');
            if (row) {
                var btn = row.querySelector('.sh-review-btn');
                if (btn) openPanel(btn.dataset.requestId);
            }
        });

        closeBtn.addEventListener('click', closeReviewPanel);
        backdrop.addEventListener('click', closeReviewPanel);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && panel.classList.contains('is-open')) {
                closeReviewPanel();
            }
        });
    });
    </script>
    @endpush
@endsection
