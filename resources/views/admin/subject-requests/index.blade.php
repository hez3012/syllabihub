@extends('layouts.app')

@section('title', 'Subject Change Requests — SyllabiHub')

@section('content')
    <p><a href="{{ route('dashboard.admin') }}">&larr; Back to Admin Dashboard</a></p>

    <h1 class="h3 mb-4">Pending Subject Change Requests</h1>

    @forelse ($requests as $req)
        <div class="border rounded p-3 mb-3">
            <p class="mb-1">
                <strong>{{ ucfirst($req->action) }}</strong> —
                {{ $req->subject->subject_code }} ({{ $req->subject->title }}) —
                requested by {{ $req->requester->name }} ({{ $req->created_at->format('Y-m-d H:i') }})
            </p>

            @if ($req->action === 'update')
                <table class="table table-sm table-bordered mb-2">
                    <thead>
                        <tr><th>Field</th><th>Current</th><th>Proposed</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($req->payload->getArrayCopy() as $field => $newValue)
                            <tr>
                                <td>{{ $field }}</td>
                                <td>{{ $req->subject->{$field} }}</td>
                                <td>{{ $newValue }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-danger mb-2">Delete request — mawawala ang subject na ito kung i-a-approve.</p>
            @endif

            <form method="POST" action="{{ route('subject-requests.approve', $req) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-success">Approve</button>
            </form>
            <form method="POST" action="{{ route('subject-requests.reject', $req) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
            </form>
        </div>
    @empty
        <p class="text-muted">Walang naka-pending na requests.</p>
    @endforelse
@endsection
