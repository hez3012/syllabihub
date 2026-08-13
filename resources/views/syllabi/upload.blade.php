@extends('layouts.app')

@section('title', 'Upload Syllabus — SyllabiHub')

@section('content')
    <p><a href="{{ route('subjects.show', $subject) }}">&larr; Back</a></p>

    <h1 class="h3 mb-2">Upload Syllabus for {{ $subject->subject_code }} — {{ $subject->title }}</h1>
    <p class="text-muted">You may upload one file or both — PDF and DOCX — as long as at least one file is provided. Uploading a new file replaces the existing file of the same type for this subject; it will not be added alongside it.</p>

    <form method="POST" action="{{ route('syllabi.store', $subject) }}" enctype="multipart/form-data" class="col-md-6">
        @csrf
        <div class="mb-3">
            <label class="form-label">PDF File (optional)</label>
            <input type="file" name="file_pdf" class="form-control" accept=".pdf">
        </div>
        <div class="mb-3">
            <label class="form-label">DOCX File (optional)</label>
            <input type="file" name="file_docx" class="form-control" accept=".docx">
        </div>
        <div class="mb-3">
            <label class="form-label">Curriculum Year (required if uploading a file)</label>
            <select name="curriculum_year" class="form-select">
                <option value="">-- Select --</option>
                @foreach ($curriculumYears as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Upload</button>
    </form>
@endsection
