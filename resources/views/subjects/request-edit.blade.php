@extends('layouts.app')

@section('title', 'Request Edit — ' . $subject->subject_code)

@section('content')
    <p><a href="{{ url()->previous(route('subjects.show', $subject)) }}">&larr; Back</a></p>

    <h1 class="h3 mb-2">Propose an Edit — {{ $subject->subject_code }}</h1>
    <p class="text-muted">The subject will not change immediately — an admin or intern must review this request before it is applied.</p>

    <form method="POST" action="{{ route('subject-requests.update', $subject) }}">
        @csrf
        @include('subjects._form')
        <button type="submit" class="btn btn-primary">Submit for Approval</button>
    </form>
@endsection
