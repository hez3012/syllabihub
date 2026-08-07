{{-- Backend test stub — NOT final UI. Frontend team owns the real design. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Syllabus stub — SyllabiHub</title>
</head>
<body>
    <h1>Upload syllabus for {{ $subject->subject_code }} — {{ $subject->title }} (test stub)</h1>

    @if ($errors->any())
        <ul style="color:red">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('syllabi.store', $subject) }}" enctype="multipart/form-data">
        @csrf
        <div>
            <label>File (PDF or DOCX, max 20MB): <input type="file" name="file" accept=".pdf,.docx" required></label>
        </div>
        <div>
            <label>Curriculum year: <input type="text" name="curriculum_year" placeholder="e.g. 2025-2026"></label>
        </div>
        <button type="submit">Upload</button>
    </form>
</body>
</html>
