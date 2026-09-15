@extends('layouts.app')

@section('title', 'Add Course')

@section('content')
    <a href="{{ route('courses.index') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Courses</a>

    <div class="sh-upload-form-header">
        <h1 class="sh-section-title">Add a New Course</h1>
        <p class="sh-upload-course-label">Fill in the course details below</p>
    </div>

    <div class="sh-upload-card">
        <form method="POST" action="{{ route('courses.store') }}" enctype="multipart/form-data">
            @csrf
            @include('courses._form', ['course' => null])

            <div class="sh-upload-field" style="margin-top:var(--space-5);padding-top:var(--space-5);border-top:1px solid var(--sh-border);">
                <label class="form-label" style="font-weight:600;">Syllabus File <span class="text-muted" style="font-weight:400;">(optional)</span></label>
                <p class="sh-upload-note" style="margin-top:0;">You may upload a file here now, or upload/replace it later from the course page.</p>
                @include('courses._syllabus-file-fields')
            </div>

            <div class="sh-upload-actions">
                <a href="{{ route('courses.index') }}" class="btn btn-pup-outline-dark">Cancel</a>
                <button type="submit" class="btn btn-pup-primary"><i class="bi bi-plus-lg"></i> Add Course</button>
            </div>
        </form>
    </div>
@endsection
