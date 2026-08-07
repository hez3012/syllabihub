@extends('layouts.app')

@section('title', 'Edit ' . $subject->subject_code . ' — SyllabiHub')

@section('content')
    <p><a href="{{ route('subjects.show', $subject) }}">&larr; Back to {{ $subject->subject_code }}</a></p>

    <h1 class="h3 mb-4">Edit {{ $subject->subject_code }}</h1>

    <form method="POST" action="{{ route('subjects.update', $subject) }}">
        @csrf
        @method('PUT')
        @include('subjects._form')
        <button type="submit" class="btn btn-primary">Save changes</button>
    </form>

    <hr class="my-4">

    <form method="POST" action="{{ route('subjects.destroy', $subject) }}" onsubmit="return confirm('Sigurado ka bang i-delete ang subject na ito?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-outline-danger">Delete subject</button>
    </form>
@endsection
