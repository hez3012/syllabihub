@extends('layouts.app')

@section('title', 'Add Course — SyllabiHub')

@section('content')
    <p><a href="{{ route('courses.index') }}">&larr; Back</a></p>

    <h1 class="h3 mb-4">Add a New Course</h1>

    <form method="POST" action="{{ route('courses.store') }}" enctype="multipart/form-data">
        @csrf
        @include('courses._form', ['course' => null])

        <hr class="my-4">
        <h2 class="h5">Syllabus File (optional)</h2>
        <p class="text-muted">You may upload a file here now, or upload/replace it later from the course page.</p>
        @include('courses._syllabus-file-fields')

        <button type="submit" class="btn btn-primary">Add Course</button>
    </form>
@endsection
