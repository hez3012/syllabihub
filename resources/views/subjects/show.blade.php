{{-- Backend test stub — NOT final UI. Frontend team owns the real design. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $subject->subject_code }} stub — SyllabiHub</title>
</head>
<body>
    @if (session('status'))
        <p style="color:green">{{ session('status') }}</p>
    @endif

    <p><a href="{{ route('subjects.index') }}">&larr; Back to Browse</a></p>

    <h1>{{ $subject->subject_code }} — {{ $subject->title }}</h1>
    <ul>
        <li>Program: {{ $subject->program?->code }}</li>
        <li>Year level: {{ $subject->year_level }}</li>
        <li>Semester: {{ $subject->semester }}</li>
        <li>Prerequisite: {{ $subject->prerequisite ?? '—' }}</li>
        <li>Co-requisite: {{ $subject->corequisite ?? '—' }}</li>
        <li>Lecture hours: {{ $subject->lecture_hours ?? '—' }}</li>
        <li>Lab hours: {{ $subject->lab_hours ?? '—' }}</li>
        <li>Units: {{ $subject->credited_units ?? '—' }}</li>
    </ul>

    <h2>Syllabi</h2>
    <ul>
        @forelse ($subject->syllabi as $syllabus)
            <li>
                {{ $syllabus->curriculum_year ?? 'N/A' }} —
                {{ strtoupper($syllabus->file_type) }} —
                status: {{ $syllabus->status }} —
                <a href="{{ route('syllabi.download', $syllabus) }}">Download</a>
            </li>
        @empty
            <li>Wala pang naka-upload na syllabus.</li>
        @endforelse
    </ul>

    <p><a href="{{ route('syllabi.create', $subject) }}">Upload/replace syllabus</a> (kailangan naka-login, admin/faculty/intern)</p>
</body>
</html>
