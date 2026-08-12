@extends('layouts.app')

@section('title', 'Subject Change Requests — SyllabiHub')

@section('content')
    <p><a href="{{ url()->previous(route('dashboard.admin')) }}">&larr; Back</a></p>

    <h1 class="h3 mb-4">Pending Subject Change Requests</h1>

    @forelse ($requests as $req)
        <div class="border rounded p-3 mb-3">
            <p class="mb-1">
                <strong>{{ ucfirst($req->action) }}</strong> —
                {{ $req->subject->subject_code }} ({{ $req->subject->title }}) —
                requested by {{ $req->requester->name }} ({{ $req->created_at->format('Y-m-d H:i') }})
            </p>

            @if ($req->action === 'update')
                @php
                    // Human-readable labels for the raw snake_case payload
                    // keys below — falls back to a humanized version of the
                    // field name itself for anything not listed here.
                    $fieldLabels = [
                        'program_id' => 'Program',
                        'subject_code' => 'Subject Code',
                        'title' => 'Title',
                        'year_level' => 'Year Level',
                        'semester' => 'Semester',
                        'prerequisite' => 'Prerequisite',
                        'corequisite' => 'Co-requisite',
                        'lecture_hours' => 'Lecture Hours',
                        'lab_hours' => 'Lab Hours',
                        'credited_units' => 'Credited Units',
                        'tuition_hours' => 'Tuition Hours',
                    ];
                @endphp
                <table class="table table-sm table-bordered mb-2">
                    <thead>
                        <tr><th>Field</th><th>Current</th><th>Proposed</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($req->payload->getArrayCopy() as $field => $newValue)
                            <tr>
                                <td>{{ $fieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field)) }}</td>
                                <td>{{ $req->subject->{$field} }}</td>
                                <td>{{ $newValue }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-danger mb-2">Delete request — this subject will be removed if approved.</p>
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
        <p class="text-muted">No pending requests.</p>
    @endforelse
@endsection
