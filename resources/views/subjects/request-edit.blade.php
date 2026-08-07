@extends('layouts.app')

@section('title', 'Request Edit — ' . $subject->subject_code)

@section('content')
    <p><a href="{{ route('subjects.show', $subject) }}">&larr; Back to {{ $subject->subject_code }}</a></p>

    <h1 class="h3 mb-2">Propose an edit — {{ $subject->subject_code }}</h1>
    <p class="text-muted">Hindi agad mababago ang subject — mare-review muna ito ng admin/intern bago ma-apply.</p>

    <form method="POST" action="{{ route('subject-requests.update', $subject) }}">
        @csrf
        @include('subjects._form')
        <button type="submit" class="btn btn-primary">Submit for approval</button>
    </form>
@endsection
