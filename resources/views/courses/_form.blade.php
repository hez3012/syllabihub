{{-- Shared fields for courses.create / courses.edit / courses.request-edit.
     Expects $programs and, when editing, $course (null on create). --}}
<div class="sh-upload-field">
    <label class="form-label">Program</label>
    <select name="program_id" class="form-select">
        <option value="">-- Select --</option>
        @foreach ($programs as $program)
            <option value="{{ $program->id }}" @selected(old('program_id', $course->program_id ?? null) == $program->id)>
                {{ $program->code }} — {{ $program->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="sh-upload-field">
            <label class="form-label">Course Code</label>
            <input type="text" name="course_code" class="form-control" value="{{ old('course_code', $course->course_code ?? '') }}" placeholder="e.g., COMP 016">
        </div>
    </div>
    <div class="col-md-6">
        <div class="sh-upload-field">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" value="{{ old('title', $course->title ?? '') }}" placeholder="e.g., Data Structures">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="sh-upload-field">
            <label class="form-label">Year Level</label>
            <select name="year_level" class="form-select">
                <option value="">-- Select --</option>
                @foreach ([1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'] as $value => $label)
                    <option value="{{ $value }}" @selected((string) old('year_level', $course->year_level ?? '') === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="sh-upload-field">
            <label class="form-label">Semester</label>
            <select name="semester" class="form-select">
                <option value="">-- Select --</option>
                @foreach (['1st', '2nd', 'summer'] as $sem)
                    <option value="{{ $sem }}" @selected(old('semester', $course->semester ?? null) === $sem)>{{ ucfirst($sem) }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="sh-upload-field">
            <label class="form-label">Prerequisite</label>
            <input type="text" name="prerequisite" class="form-control" value="{{ old('prerequisite', $course->prerequisite ?? '') }}" placeholder="Optional">
        </div>
    </div>
    <div class="col-md-3">
        <div class="sh-upload-field">
            <label class="form-label">Co-requisite</label>
            <input type="text" name="corequisite" class="form-control" value="{{ old('corequisite', $course->corequisite ?? '') }}" placeholder="Optional">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="sh-upload-field">
            <label class="form-label">Lecture Hours</label>
            <input type="number" step="0.5" name="lecture_hours" class="form-control" value="{{ old('lecture_hours', $course->lecture_hours ?? '') }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="sh-upload-field">
            <label class="form-label">Lab Hours</label>
            <input type="number" step="0.5" name="lab_hours" class="form-control" value="{{ old('lab_hours', $course->lab_hours ?? '') }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="sh-upload-field">
            <label class="form-label">Credited Units</label>
            <input type="number" step="0.5" name="credited_units" class="form-control" value="{{ old('credited_units', $course->credited_units ?? '') }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="sh-upload-field">
            <label class="form-label">Tuition Hours</label>
            <input type="number" step="0.5" name="tuition_hours" class="form-control" value="{{ old('tuition_hours', $course->tuition_hours ?? '') }}">
        </div>
    </div>
</div>
