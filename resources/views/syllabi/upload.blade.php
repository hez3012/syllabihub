@extends('layouts.app')

@section('title', 'Upload Syllabus — SyllabiHub')

@section('content')
    <p><a href="{{ route('subjects.show', $subject) }}">&larr; Back to {{ $subject->subject_code }}</a></p>

    <h1 class="h3 mb-2">Upload syllabus for {{ $subject->subject_code }} — {{ $subject->title }}</h1>
    <p class="text-muted">Puwedeng isa lang o pareho — PDF at DOCX — basta may kahit isang file.</p>

    <form method="POST" action="{{ route('syllabi.store', $subject) }}" enctype="multipart/form-data" class="col-md-6">
        @csrf
        <div class="mb-3">
            <label class="form-label">PDF file (optional)</label>
            <input type="file" name="file_pdf" class="form-control" accept=".pdf">
        </div>
        <div class="mb-3">
            <label class="form-label">DOCX file (optional)</label>
            <input type="file" name="file_docx" class="form-control" accept=".docx">
        </div>
        <div class="mb-3">
            <label class="form-label">Curriculum year</label>
            <select name="curriculum_year" class="form-select">
                <option value="">-- pumili --</option>
                @foreach ($curriculumYears as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Upload</button>
    </form>
@endsection
