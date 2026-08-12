@extends('layouts.app')

@section('title', 'Add Subject — SyllabiHub')

@section('content')
    <p><a href="{{ url()->previous(route('subjects.index')) }}">&larr; Back</a></p>

    <h1 class="h3 mb-4">Add a New Subject</h1>

    <form method="POST" action="{{ route('subjects.store') }}" enctype="multipart/form-data">
        @csrf
        @include('subjects._form', ['subject' => null])

        <hr class="my-4">
        <h2 class="h5">Syllabus File (optional)</h2>
        <p class="text-muted">You may upload a file here now, or upload/replace it later from the subject page.</p>
        @include('subjects._syllabus-file-fields')

        <button type="submit" class="btn btn-primary">Add Subject</button>
    </form>
@endsection
