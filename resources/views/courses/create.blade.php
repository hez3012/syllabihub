@extends('layouts.app')

@section('title', 'Add Course — SyllabiHub')

@section('content')
    <a href="{{ route('courses.index') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Courses</a>

    <div class="page-hero">
        <div class="eyebrow">New Entry</div>
        <h1 class="h3 mb-0">Add a New Course</h1>
    </div>

    <div class="content-card">
        <form method="POST" action="{{ route('courses.store') }}" enctype="multipart/form-data">
            @csrf
            @include('courses._form', ['course' => null])

            <div class="form-section-title">Syllabus File (optional)</div>
            <p class="form-hint">You may upload a file here now, or upload/replace it later from the course page.</p>
            @include('courses._syllabus-file-fields')

            <button type="submit" class="btn btn-pup-primary mt-2"><i class="bi bi-plus-lg"></i> Add Course</button>
        </form>
    </div>
@endsection
