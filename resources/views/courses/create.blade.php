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

            <fieldset class="sh-form-section">
                <legend class="sh-form-section-head">
                    <span class="sh-form-section-badge">5</span>
                    <span class="sh-form-section-titles">
                        <span class="sh-form-section-title">Syllabus File <span class="sh-label-chip">Optional</span></span>
                        <span class="sh-form-section-hint">Attach a syllabus now, or upload it later from the course page.</span>
                    </span>
                </legend>
                <div class="sh-form-section-body">
                    @include('courses._syllabus-file-fields')
                </div>
            </fieldset>

            <div class="sh-upload-actions">
                <a href="{{ route('courses.index') }}" class="btn btn-pup-outline-dark">Cancel</a>
                <button type="submit" class="btn btn-pup-primary"><i class="bi bi-plus-lg"></i> Add Course</button>
            </div>
        </form>
    </div>
@endsection
