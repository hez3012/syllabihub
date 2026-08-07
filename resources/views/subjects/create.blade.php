@extends('layouts.app')

@section('title', 'Add Subject — SyllabiHub')

@section('content')
    <p><a href="{{ route('subjects.index') }}">&larr; Back to Browse</a></p>

    <h1 class="h3 mb-4">Add a new subject</h1>

    <form method="POST" action="{{ route('subjects.store') }}">
        @csrf
        @include('subjects._form', ['subject' => null])
        <button type="submit" class="btn btn-primary">Add subject</button>
    </form>
@endsection
