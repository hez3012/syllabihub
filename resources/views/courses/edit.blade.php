@extends('layouts.app')

@section('title', 'Edit ' . $course->course_code . ' — SyllabiHub')

@section('content')
    <p><a href="{{ route('courses.show', $course) }}">&larr; Back</a></p>

    <h1 class="h3 mb-4">Edit {{ $course->course_code }}</h1>

    <form method="POST" action="{{ route('courses.update', $course) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('courses._form')

        <hr class="my-4">
        <h2 class="h5">Syllabus File</h2>

        @if ($course->syllabi->isNotEmpty())
            <p class="text-muted mb-2">Currently uploaded:</p>
            <ul class="list-group mb-3" style="max-width: 32rem;">
                @foreach ($course->syllabi as $syllabus)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>{{ strtoupper($syllabus->file_type) }}: {{ $syllabus->original_filename ?? basename($syllabus->file_path) }}</span>
                        <span class="badge bg-secondary">{{ $syllabus->curriculum_year ?? 'N/A' }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-muted mb-2">No syllabus has been uploaded yet.</p>
        @endif

        <p class="text-muted">Uploading a new file below replaces the existing file of the same type — it will not be added alongside it.</p>
        @include('courses._syllabus-file-fields')

        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>

    <hr class="my-4">

    <form method="POST" action="{{ route('courses.destroy', $course) }}" onsubmit="return confirm('Are you sure you want to delete this course?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Delete Course</button>
    </form>
@endsection
