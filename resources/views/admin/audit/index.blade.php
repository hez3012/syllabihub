@extends('layouts.app')

@section('title', 'Audit Logs & Trails')

@section('content')
    {{-- Tab navigation --}}
    <div class="sh-audit-tabs">
        <a href="{{ route('audit.index', ['tab' => 'logs']) }}"
           class="sh-audit-tab {{ $activeTab === 'logs' ? 'active' : '' }}">
            <i class="bi bi-box-arrow-in-right"></i> Login Activity
        </a>
        <a href="{{ route('audit.index', ['tab' => 'trails']) }}"
           class="sh-audit-tab {{ $activeTab === 'trails' ? 'active' : '' }}">
            <i class="bi bi-pencil-square"></i> Change History
        </a>
    </div>

    {{-- Audit Logs Tab --}}
    @if ($activeTab === 'logs')
        <div class="sh-audit-content">
            @if ($logs->isEmpty())
                <div class="sh-empty-state-branded">
                    <div class="sh-empty-state-icon">
                        <i class="bi bi-box-arrow-in-right"></i>
                    </div>
                    <p class="sh-empty-state-title">No login activity yet</p>
                    <p class="sh-empty-state-desc">Login and logout events will appear here.</p>
                </div>
            @else
                <div class="sh-table-wrap">
                    <table class="sh-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Action</th>
                                <th>IP Address</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                <tr>
                                    <td class="sh-table-name">{{ $log->full_name }}</td>
                                    <td class="sh-table-email">{{ $log->email }}</td>
                                    <td>
                                        <span class="sh-badge sh-badge-{{ $log->action === 'login' ? 'success' : 'warning' }}">
                                            <i class="bi bi-{{ $log->action === 'login' ? 'box-arrow-in-right' : 'box-arrow-left' }}"></i>
                                            {{ ucfirst($log->action) }}
                                        </span>
                                    </td>
                                    <td class="sh-table-mono">{{ $log->ip_address ?? '—' }}</td>
                                    <td class="sh-table-date">{{ $log->created_at?->format('M d, Y h:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="sh-pagination">
                    {{ $logs->withQueryString()->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- Audit Trails Tab --}}
    @if ($activeTab === 'trails')
        <div class="sh-audit-content">
            @if ($trails->isEmpty())
                <div class="sh-empty-state-branded">
                    <div class="sh-empty-state-icon">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <p class="sh-empty-state-title">No changes recorded yet</p>
                    <p class="sh-empty-state-desc">Course and syllabus changes will appear here.</p>
                </div>
            @else
                <div class="sh-table-wrap">
                    <table class="sh-table">
                        <thead>
                            <tr>
                                <th>Who</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>Changes</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($trails as $trail)
                                <tr>
                                    <td class="sh-table-name">{{ $trail->full_name }}</td>
                                    <td>
                                        <span class="sh-badge sh-badge-{{ $trail->action === 'created' ? 'success' : ($trail->action === 'deleted' ? 'danger' : 'info') }}">
                                            <i class="bi bi-{{ $trail->action === 'created' ? 'plus-circle' : ($trail->action === 'deleted' ? 'trash' : 'pencil') }}"></i>
                                            {{ ucfirst($trail->action) }}
                                        </span>
                                    </td>
                                    <td class="sh-table-details">{{ $trail->description }}</td>
                                    <td>
                                        @if ($trail->old_values || $trail->new_values)
                                            <button type="button" class="sh-trail-toggle" onclick="this.nextElementSibling.classList.toggle('d-none'); this.textContent = this.textContent === 'Show' ? 'Hide' : 'Show';">
                                                Show
                                            </button>
                                            <div class="sh-trail-diff d-none">
                                                @if ($trail->old_values)
                                                    @foreach ($trail->old_values as $key => $oldVal)
                                                        @php
                                                            $newVal = $trail->new_values[$key] ?? null;
                                                        @endphp
                                                        <div class="sh-diff-row">
                                                            <span class="sh-diff-key">{{ $key }}</span>
                                                            <span class="sh-diff-old">{{ is_array($oldVal) ? json_encode($oldVal) : $oldVal }}</span>
                                                            <i class="bi bi-arrow-right"></i>
                                                            <span class="sh-diff-new">{{ is_array($newVal) ? json_encode($newVal) : $newVal }}</span>
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        @else
                                            <span class="sh-text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="sh-table-date">{{ $trail->created_at?->format('M d, Y h:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="sh-pagination">
                    {{ $trails->withQueryString()->links() }}
                </div>
            @endif
        </div>
    @endif
@endsection
