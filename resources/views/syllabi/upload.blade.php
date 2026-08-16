@extends('layouts.app')

@section('title', 'Upload Syllabus — SyllabiHub')

@section('content')
    <a href="{{ route('courses.show', $course) }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to {{ $course->course_code }}</a>

    <div class="page-hero">
        <div class="eyebrow">Syllabus Upload</div>
        <h1 class="h3 mb-0">{{ $course->course_code }} — {{ $course->title }}</h1>
    </div>

    <div class="content-card">
        <p class="form-hint">You may upload one file or both — PDF and DOCX — as long as at least one file is provided. Uploading a new file replaces the existing file of the same type for this course; it will not be added alongside it.</p>

        <form method="POST" action="{{ route('syllabi.store', $course) }}" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">PDF File (optional)</label>
                    <input type="file" name="file_pdf" class="form-control" accept=".pdf">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">DOCX File (optional)</label>
                    <input type="file" name="file_docx" class="form-control" accept=".docx">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Curriculum Year (required if uploading a file)</label>
                    <select name="curriculum_year" class="form-select">
                        <option value="">-- Select --</option>
                        @foreach ($curriculumYears as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-pup-primary mt-2"><i class="bi bi-upload"></i> Upload</button>
        </form>
    </div>
@endsection
