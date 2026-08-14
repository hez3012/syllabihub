{{-- Shared fields for courses.create / courses.edit / courses.request-edit.
     Expects $programs and, when editing, $course (null on create).

     Deliberately NO client-side `required` on these — Rico, 2026-08-12,
     wants an actual in-app error (the red alert box in layouts.app) when a
     required field is left blank, not a silent browser-native tooltip that
     blocks the request before it ever reaches CourseController's server-
     side validation. Semester also got a blank default option added for
     the same reason: without one it silently defaulted to "1st", so it
     could never actually be submitted empty to trigger that error. --}}
<div class="row">
    <div class="col-md-4 mb-3">
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
    <div class="col-md-4 mb-3">
        <label class="form-label">Course Code</label>
        <input type="text" name="course_code" class="form-control" value="{{ old('course_code', $course->course_code ?? '') }}" placeholder="e.g. COMP 016">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" value="{{ old('title', $course->title ?? '') }}">
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-3">
        <label class="form-label">Year Level</label>
        {{-- Dropdown, 1st-4th Year only — Rico, 2026-08-12. --}}
        <select name="year_level" class="form-select">
            <option value="">-- Select --</option>
            @foreach ([1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'] as $value => $label)
                <option value="{{ $value }}" @selected((string) old('year_level', $course->year_level ?? '') === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Semester</label>
        <select name="semester" class="form-select">
            <option value="">-- Select --</option>
            @foreach (['1st', '2nd', 'summer'] as $sem)
                <option value="{{ $sem }}" @selected(old('semester', $course->semester ?? null) === $sem)>{{ ucfirst($sem) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Prerequisite (optional)</label>
        <input type="text" name="prerequisite" class="form-control" value="{{ old('prerequisite', $course->prerequisite ?? '') }}">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Co-requisite (optional)</label>
        <input type="text" name="corequisite" class="form-control" value="{{ old('corequisite', $course->corequisite ?? '') }}">
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-3">
        <label class="form-label">Lecture Hours</label>
        <input type="number" step="0.5" name="lecture_hours" class="form-control" value="{{ old('lecture_hours', $course->lecture_hours ?? '') }}">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Lab Hours</label>
        <input type="number" step="0.5" name="lab_hours" class="form-control" value="{{ old('lab_hours', $course->lab_hours ?? '') }}">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Credited Units</label>
        <input type="number" step="0.5" name="credited_units" class="form-control" value="{{ old('credited_units', $course->credited_units ?? '') }}">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Tuition Hours</label>
        <input type="number" step="0.5" name="tuition_hours" class="form-control" value="{{ old('tuition_hours', $course->tuition_hours ?? '') }}">
    </div>
</div>
