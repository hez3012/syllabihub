@extends('layouts.app')

@section('title', 'Edit ' . $course->course_code)

@section('content')
    <a href="{{ route('courses.show', $course) }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to {{ $course->course_code }}</a>

    <div class="sh-upload-form-header">
        <h1 class="sh-section-title">{{ $course->course_code }} — {{ $course->title }}</h1>
        <p class="sh-upload-course-label">Editing course details</p>
    </div>

    <div class="sh-upload-card">
        <form method="POST" action="{{ route('courses.update', $course) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('courses._form')

            <div class="sh-upload-field" style="margin-top:var(--space-5);padding-top:var(--space-5);border-top:1px solid var(--sh-border);">
                <label class="form-label" style="font-weight:600;">Syllabus Files</label>

                @if ($course->syllabi->isNotEmpty())
                    <div style="margin-bottom:var(--space-3);">
                        @foreach ($course->syllabi as $syllabus)
                            <div class="sh-panel-syllabus-item" style="margin-bottom:var(--space-2);">
                                <div class="sh-panel-syllabus-info">
                                    <i class="bi bi-file-earmark-text" style="color:var(--sh-red);"></i>
                                    <span class="sh-panel-syllabus-filename">{{ strtoupper($syllabus->file_type) }}: {{ $syllabus->original_filename ?? basename($syllabus->file_path) }}</span>
                                    <span class="sh-panel-syllabus-year">{{ $syllabus->curriculum_year ?? 'N/A' }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="sh-upload-note" style="margin-top:0;">No syllabus has been uploaded yet.</p>
                @endif

                <p class="sh-upload-note" style="margin-top:0;">Uploading a new file below replaces the existing file of the same type.</p>
                @include('courses._syllabus-file-fields')
            </div>

            <div class="sh-upload-actions">
                <a href="{{ route('courses.show', $course) }}" class="btn btn-pup-outline-dark">Cancel</a>
                <button type="submit" class="btn btn-pup-primary"><i class="bi bi-check-lg"></i> Save Changes</button>
            </div>
        </form>
    </div>

    <div class="sh-upload-card" style="margin-top:var(--space-4);border-color:var(--sh-danger);">
        <h3 style="color:var(--sh-danger);font-size:var(--text-base);font-weight:600;margin:0 0 var(--space-2);">Danger Zone</h3>
        <p class="sh-upload-note" style="margin-top:0;">Deleting a course removes it and its syllabi permanently. This cannot be undone.</p>
        <form method="POST" action="{{ route('courses.destroy', $course) }}" onsubmit="return confirm('Are you sure you want to delete this course?');" style="margin:0;">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-pup-danger"><i class="bi bi-trash"></i> Delete Course</button>
        </form>
    </div>
@endsection
