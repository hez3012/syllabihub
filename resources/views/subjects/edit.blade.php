@extends('layouts.app')

@section('title', 'Edit ' . $subject->subject_code . ' — SyllabiHub')

@section('content')
    <p><a href="{{ url()->previous(route('subjects.show', $subject)) }}">&larr; Back</a></p>

    <h1 class="h3 mb-4">Edit {{ $subject->subject_code }}</h1>

    <form method="POST" action="{{ route('subjects.update', $subject) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('subjects._form')

        <hr class="my-4">
        <h2 class="h5">Syllabus File</h2>

        @if ($subject->syllabi->isNotEmpty())
            <p class="text-muted mb-2">Currently uploaded:</p>
            <ul class="list-group mb-3" style="max-width: 32rem;">
                @foreach ($subject->syllabi as $syllabus)
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
        @include('subjects._syllabus-file-fields')

        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>

    <hr class="my-4">

    <form method="POST" action="{{ route('subjects.destroy', $subject) }}" onsubmit="return confirm('Are you sure you want to delete this subject?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Delete Subject</button>
    </form>
@endsection
