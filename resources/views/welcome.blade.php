@extends('layouts.guest')

@section('title', 'SyllabiHub — Course Syllabus Repository')

@section('content')
    <div style="text-align: center;">
        <h1 class="auth-title" style="text-align: center;">Get Started!</h1>
        <h2 class="auth-subtitle" style="text-align: center;">SyllabiHub</h2>

        <div class="get-started-info">
            <p>Course Syllabus Repository for BSIT & DIT</p>
        </div>

        <a href="{{ route('courses.index') }}" class="btn btn-pup-primary w-100" style="margin-top: var(--space-4); justify-content: center;">
            Proceed to SyllabiHub.
        </a>

        <div style="margin-top: var(--space-6); padding-top: var(--space-4); border-top: 1px solid var(--sh-border);">
            <p style="font-size: var(--text-xs); color: var(--sh-text-muted); margin: 0;">
                PUP Taguig · Polytechnic University of the Philippines
            </p>
        </div>
    </div>
@endsection
