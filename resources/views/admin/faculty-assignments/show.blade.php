{{-- Backend test stub — NOT final UI. Frontend team owns the real design. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage {{ $faculty->name }} stub — SyllabiHub</title>
</head>
<body>
    <p><a href="{{ route('faculty-assignments.index') }}">&larr; Back to Faculty Accounts</a></p>

    @if (session('status'))
        <p style="color:green">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <ul style="color:red">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <h1>{{ $faculty->name }} ({{ $faculty->email }}) — assigned subjects (test stub)</h1>

    <table border="1" cellpadding="4">
        <thead>
            <tr>
                <th>Code</th>
                <th>Title</th>
                <th>Program</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($faculty->subjects as $subject)
                <tr>
                    <td>{{ $subject->subject_code }}</td>
                    <td>{{ $subject->title }}</td>
                    <td>{{ $subject->program?->code }}</td>
                    <td>
                        <form method="POST" action="{{ route('faculty-assignments.destroy', [$faculty, $subject]) }}" style="display:inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">Wala pang naka-assign na subjects.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Assign a subject</h2>
    <form method="POST" action="{{ route('faculty-assignments.store', $faculty) }}">
        @csrf
        <label>Subject:
            <select name="subject_id" required>
                <option value="">-- pumili --</option>
                @foreach ($availableSubjects as $subject)
                    <option value="{{ $subject->id }}">{{ $subject->subject_code }} — {{ $subject->title }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit">Assign</button>
    </form>
</body>
</html>
