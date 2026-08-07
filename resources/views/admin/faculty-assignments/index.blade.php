{{-- Backend test stub — NOT final UI. Frontend team owns the real design. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Faculty Assignments stub — SyllabiHub</title>
</head>
<body>
    <p><a href="{{ route('dashboard.admin') }}">&larr; Back to Admin Dashboard</a></p>

    <h1>Faculty Accounts (test stub)</h1>

    <table border="1" cellpadding="4">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th># Subjects assigned</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($faculty as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->subjects_count }}</td>
                    <td><a href="{{ route('faculty-assignments.show', $user) }}">Manage subjects</a></td>
                </tr>
            @empty
                <tr><td colspan="4">Walang faculty accounts.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
