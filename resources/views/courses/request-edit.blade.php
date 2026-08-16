@extends('layouts.app')

@section('title', 'Request Edit — ' . $course->course_code)

@section('content')
    <a href="{{ route('courses.show', $course) }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to {{ $course->course_code }}</a>

    <div class="page-hero">
        <div class="eyebrow">Change Request</div>
        <h1 class="h3 mb-0">Propose an Edit — {{ $course->course_code }}</h1>
    </div>

    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="bi bi-info-circle mt-1"></i>
        <span>The course will not change immediately — an admin or intern must review this request before it is applied.</span>
    </div>

    <div class="content-card">
        <form method="POST" action="{{ route('course-requests.update', $course) }}">
            @csrf
            @include('courses._form')
            <button type="submit" class="btn btn-pup-primary mt-2"><i class="bi bi-send"></i> Submit for Approval</button>
        </form>
    </div>
@endsection
