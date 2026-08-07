{{-- Backend test stub — NOT final UI. Frontend team owns the real design. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Faculty Dashboard stub — SyllabiHub</title>
</head>
<body>
    <h1>My Subjects (test stub)</h1>
    <p>Logged in as: {{ auth()->user()->name }} ({{ auth()->user()->email }})</p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Log out</button>
    </form>

    <table border="1" cellpadding="4">
        <thead>
            <tr>
                <th>Code</th>
                <th>Title</th>
                <th>Syllabus status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($subjects as $subject)
                <tr>
                    <td>{{ $subject->subject_code }}</td>
                    <td>{{ $subject->title }}</td>
                    <td>{{ $subject->latestSyllabus->status ?? 'wala pa' }}</td>
                    <td><a href="{{ route('syllabi.create', $subject) }}">Upload/Replace</a></td>
                </tr>
            @empty
                <tr><td colspan="4">Walang subjects na naka-assign sa iyo.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
