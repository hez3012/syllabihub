@extends('layouts.app')

@section('title', 'Edit ' . $course->course_code . ' — SyllabiHub')

@section('content')
    <a href="{{ route('courses.show', $course) }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to {{ $course->course_code }}</a>

    <div class="page-hero">
        <div class="eyebrow">Editing</div>
        <h1 class="h3 mb-0">{{ $course->course_code }} — {{ $course->title }}</h1>
    </div>

    <div class="content-card mb-4">
        <form method="POST" action="{{ route('courses.update', $course) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('courses._form')

            <div class="form-section-title">Syllabus File</div>

            @if ($course->syllabi->isNotEmpty())
                <p class="form-hint mb-2">Currently uploaded:</p>
                <div class="file-list">
                    @foreach ($course->syllabi as $syllabus)
                        <div class="file-list-item">
                            <span class="file-name"><i class="bi bi-file-earmark-text me-1"></i>{{ strtoupper($syllabus->file_type) }}: {{ $syllabus->original_filename ?? basename($syllabus->file_path) }}</span>
                            <span class="badge bg-secondary">{{ $syllabus->curriculum_year ?? 'N/A' }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="form-hint mb-2">No syllabus has been uploaded yet.</p>
            @endif

            <p class="form-hint">Uploading a new file below replaces the existing file of the same type — it will not be added alongside it.</p>
            @include('courses._syllabus-file-fields')

            <button type="submit" class="btn btn-pup-primary mt-2"><i class="bi bi-check-lg"></i> Save Changes</button>
        </form>
    </div>

    <div class="content-card">
        <h2 class="section-heading text-danger">Danger Zone</h2>
        <p class="form-hint mb-3">Deleting a course removes it and its syllabi permanently. This cannot be undone.</p>
        <form method="POST" action="{{ route('courses.destroy', $course) }}" onsubmit="return confirm('Are you sure you want to delete this course?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-pup-danger"><i class="bi bi-trash"></i> Delete Course</button>
        </form>
    </div>
@endsection
