@extends('layouts.app')

@section('title', 'Edit ' . $course->course_code)

@section('content')
    <a href="{{ route('courses.index') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Courses</a>

    <div class="sh-upload-form-header">
        <h1 class="sh-section-title">{{ $course->course_code }} — {{ $course->title }}</h1>
        <p class="sh-upload-course-label">Editing course details</p>
    </div>

    <div class="sh-upload-card">
        <form method="POST" action="{{ route('courses.update', $course) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('courses._form')

            <fieldset class="sh-form-section">
                <legend class="sh-form-section-head">
                    <span class="sh-form-section-badge">5</span>
                    <span class="sh-form-section-titles">
                        <span class="sh-form-section-title">Syllabus File</span>
                        <span class="sh-form-section-hint">Uploading a new file replaces the existing file of the same type.</span>
                    </span>
                </legend>
                <div class="sh-form-section-body">
                    @if ($course->syllabi->isNotEmpty())
                        <div class="sh-upload-field">
                            <label class="form-label">Currently on file</label>
                            <div>
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
                        </div>
                    @else
                        <div class="sh-upload-field">
                            <p class="sh-field-hint" style="margin:0;">No syllabus has been uploaded yet.</p>
                        </div>
                    @endif

                    @include('courses._syllabus-file-fields')
                </div>
            </fieldset>

            <div class="sh-upload-actions">
                <a href="{{ route('courses.index') }}" class="btn btn-pup-outline-dark">Cancel</a>
                <button type="submit" class="btn btn-pup-primary"><i class="bi bi-check-lg"></i> Save Changes</button>
            </div>
        </form>
    </div>

    <div class="sh-upload-card sh-card-danger" style="margin-top:var(--space-4);">
        <div class="sh-danger-head">
            <span class="sh-form-section-badge sh-form-section-badge-danger">!</span>
            <span class="sh-form-section-title" style="color:var(--sh-danger);">Danger Zone</span>
        </div>
        <p class="sh-upload-note" style="margin-top:0;">Deleting a course removes it and its syllabi permanently. This cannot be undone.</p>
        <form method="POST" action="{{ route('courses.destroy', $course) }}" onsubmit="return confirm('Are you sure you want to delete this course?');" style="margin:0;">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-pup-danger"><i class="bi bi-trash"></i> Delete Course</button>
        </form>
    </div>
@endsection
