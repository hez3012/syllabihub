@extends('layouts.app')

@section('title', 'Faculty Accounts — SyllabiHub')

@section('content')
    <p><a href="{{ route('dashboard.admin') }}">&larr; Back to Admin Dashboard</a></p>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Faculty Accounts</h1>
        <a href="{{ route('faculty-accounts.create') }}" class="btn btn-primary btn-sm">+ Create Faculty Account</a>
    </div>

    <table class="table table-striped table-bordered align-middle">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th># Subjects created</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($faculty as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->created_subjects_count }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted">Wala pang faculty accounts.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
