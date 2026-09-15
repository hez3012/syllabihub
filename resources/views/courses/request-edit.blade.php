@extends('layouts.app')

@section('title', 'Request Edit — ' . $course->course_code)

@section('content')
    <a href="{{ route('courses.show', $course) }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to {{ $course->course_code }}</a>

    <div class="sh-upload-form-header">
        <h1 class="sh-section-title">Propose an Edit — {{ $course->course_code }}</h1>
        <p class="sh-upload-course-label">Submit changes for admin approval</p>
    </div>

    <div class="sh-upload-card" style="max-width:720px;">
        <div class="sh-pending-banner" style="margin-bottom:var(--space-5);">
            <div class="sh-pending-banner-content">
                <i class="bi bi-info-circle"></i>
                <span>The course will not change immediately — an admin or intern must review this request before it is applied.</span>
            </div>
        </div>

        <form method="POST" action="{{ route('course-requests.update', $course) }}">
            @csrf
            @include('courses._form')

            <div class="sh-upload-actions">
                <a href="{{ route('courses.show', $course) }}" class="btn btn-pup-outline-dark">Cancel</a>
                <button type="submit" class="btn btn-pup-primary"><i class="bi bi-send"></i> Submit for Approval</button>
            </div>
        </form>
    </div>
@endsection
