@extends('layouts.app')

@section('title', 'Faculty Accounts — SyllabiHub')

@section('content')
    <a href="{{ route('dashboard.admin') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>

    <div class="page-hero d-flex justify-content-between align-items-end flex-wrap gap-2">
        <div>
            <div class="eyebrow">Admin</div>
            <h1 class="h3 mb-0">Faculty Accounts</h1>
        </div>
        <a href="{{ route('faculty-accounts.create') }}" class="btn btn-pup-primary btn-sm"><i class="bi bi-person-plus"></i> Create Faculty Account</a>
    </div>

    <div class="courses-table-wrap">
        <table class="table courses-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th># Courses Created</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($faculty as $user)
                    <tr>
                        <td class="fw-medium">{{ $user->name }}</td>
                        <td class="text-muted">{{ $user->email }}</td>
                        <td><span class="course-code-tag">{{ $user->created_courses_count }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="p-0">
                        <div class="empty-state">
                            <i class="bi bi-people"></i>
                            <p>No faculty accounts yet.</p>
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
