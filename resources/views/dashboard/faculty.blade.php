@extends('layouts.app')

@section('title', 'Faculty Dashboard — SyllabiHub')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Subjects</h1>
        <a href="{{ route('subjects.create') }}" class="btn btn-primary btn-sm">+ Add Subject</a>
    </div>

    @if ($pendingRequests->isNotEmpty())
        <h2 class="h6">My pending requests</h2>
        <ul class="list-group mb-4">
            @foreach ($pendingRequests as $req)
                <li class="list-group-item">
                    {{ ucfirst($req->action) }} — {{ $req->subject->subject_code }} — naghihintay ng admin approval
                </li>
            @endforeach
        </ul>
    @endif

    <table class="table table-striped table-bordered align-middle">
        <thead>
            <tr>
                <th>Code</th>
                <th>Title</th>
                <th>Syllabus status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($subjects as $subject)
                <tr>
                    <td>{{ $subject->subject_code }}</td>
                    <td><a href="{{ route('subjects.show', $subject) }}">{{ $subject->title }}</a></td>
                    <td>{{ $subject->latestSyllabus->status ?? 'wala pa' }}</td>
                    <td><a href="{{ route('syllabi.create', $subject) }}" class="btn btn-sm btn-outline-primary">Upload/Replace</a></td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted">Wala ka pang nagagawang subject.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
