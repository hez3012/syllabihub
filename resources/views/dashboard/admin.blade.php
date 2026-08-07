{{-- Backend test stub — NOT final UI. Frontend team owns the real design. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard stub — SyllabiHub</title>
</head>
<body>
    <h1>Admin/Intern Dashboard (test stub)</h1>
    <p>Logged in as: {{ auth()->user()->name }} ({{ auth()->user()->email }}, role: {{ auth()->user()->role }})</p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Log out</button>
    </form>

    <p><a href="{{ route('faculty-assignments.index') }}">Manage faculty subject assignments</a></p>

    <h2>Tracker</h2>
    <ul>
        <li>Total subjects: {{ $totalSubjects }}</li>
        <li>With syllabus: {{ $withSyllabus }}</li>
        <li>Missing: {{ $missing }}</li>
    </ul>

    <h2>Recent uploads</h2>
    <table border="1" cellpadding="4">
        <thead>
            <tr>
                <th>Subject</th>
                <th>Status</th>
                <th>Uploaded by</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recentUploads as $syllabus)
                <tr>
                    <td>{{ $syllabus->subject?->subject_code }}</td>
                    <td>{{ $syllabus->status }}</td>
                    <td>{{ $syllabus->uploader?->name ?? '—' }}</td>
                    <td>{{ $syllabus->created_at?->format('Y-m-d H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Wala pang uploads.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
