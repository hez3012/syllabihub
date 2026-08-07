{{-- Backend test stub — NOT final UI. Frontend team owns the real design. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Browse Subjects stub — SyllabiHub</title>
</head>
<body>
    <h1>Browse Subjects (test stub)</h1>

    <form method="GET" action="{{ route('subjects.index') }}">
        <label>Program:
            <select name="program">
                <option value="">-- any --</option>
                <option value="BSIT" @selected(($filters['program'] ?? null) === 'BSIT')>BSIT</option>
                <option value="DIT" @selected(($filters['program'] ?? null) === 'DIT')>DIT</option>
            </select>
        </label>
        <label>Year level:
            <input type="number" name="year_level" min="1" max="10" value="{{ $filters['year_level'] ?? '' }}">
        </label>
        <label>Semester:
            <select name="semester">
                <option value="">-- any --</option>
                <option value="1st" @selected(($filters['semester'] ?? null) === '1st')>1st</option>
                <option value="2nd" @selected(($filters['semester'] ?? null) === '2nd')>2nd</option>
                <option value="summer" @selected(($filters['semester'] ?? null) === 'summer')>summer</option>
            </select>
        </label>
        <button type="submit">Filter</button>
    </form>

    <table border="1" cellpadding="4">
        <thead>
            <tr>
                <th>Code</th>
                <th>Title</th>
                <th>Program</th>
                <th>Year</th>
                <th>Semester</th>
                <th>Syllabus?</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($subjects as $subject)
                <tr>
                    <td>{{ $subject->subject_code }}</td>
                    <td><a href="{{ route('subjects.show', $subject) }}">{{ $subject->title }}</a></td>
                    <td>{{ $subject->program?->code }}</td>
                    <td>{{ $subject->year_level }}</td>
                    <td>{{ $subject->semester }}</td>
                    <td>{{ $subject->latestSyllabus ? 'Yes' : 'No' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Walang subjects na tumugma.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $subjects->links() }}
</body>
</html>
