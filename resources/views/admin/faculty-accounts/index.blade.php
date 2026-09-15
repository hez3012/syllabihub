@extends('layouts.app')

@section('title', 'Faculty Accounts')

@section('content')
    <div class="sh-section-header">
        <div>
            <h1 class="sh-section-title">Faculty Accounts</h1>
            <p class="text-muted" style="margin: var(--space-1) 0 0; font-size: var(--text-sm);">Manage faculty login accounts</p>
        </div>
        <a href="{{ route('faculty-accounts.create') }}" class="btn btn-pup-primary btn-sm">
            <i class="bi bi-person-plus"></i> Create Account
        </a>
    </div>

    <div class="courses-table-wrap">
        <table class="table courses-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Courses</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($faculty as $user)
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:var(--space-3);">
                                <div class="sh-avatar-sm">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                                <span class="sh-table-link">{{ $user->name }}</span>
                            </div>
                        </td>
                        <td class="text-muted">{{ $user->email }}</td>
                        <td>
                            <span class="sh-badge sh-badge-muted">{{ $user->created_courses_count }} courses</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">
                            <div class="empty-state">
                                <i class="bi bi-people"></i>
                                <h3>No faculty accounts</h3>
                                <p>Create a faculty account to get started.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @push('styles')
    <style>
        .sh-avatar-sm {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-md);
            background: var(--sh-red);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--text-sm);
            font-weight: 600;
            flex-shrink: 0;
        }
    </style>
    @endpush
@endsection
