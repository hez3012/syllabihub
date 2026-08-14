@extends('layouts.app')

@section('title', 'Request Edit — ' . $course->course_code)

@section('content')
    <p><a href="{{ route('courses.show', $course) }}">&larr; Back</a></p>

    <h1 class="h3 mb-2">Propose an Edit — {{ $course->course_code }}</h1>
    <p class="text-muted">The course will not change immediately — an admin or intern must review this request before it is applied.</p>

    <form method="POST" action="{{ route('course-requests.update', $course) }}">
        @csrf
        @include('courses._form')
        <button type="submit" class="btn btn-primary">Submit for Approval</button>
    </form>
@endsection
