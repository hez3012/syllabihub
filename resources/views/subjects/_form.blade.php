{{-- Shared fields for subjects.create / subjects.edit / subjects.request-edit.
     Expects $programs and, when editing, $subject (null on create). --}}
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Program</label>
        <select name="program_id" class="form-select" required>
            <option value="">-- pumili --</option>
            @foreach ($programs as $program)
                <option value="{{ $program->id }}" @selected(old('program_id', $subject->program_id ?? null) == $program->id)>
                    {{ $program->code }} — {{ $program->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Subject code</label>
        <input type="text" name="subject_code" class="form-control" value="{{ old('subject_code', $subject->subject_code ?? '') }}" placeholder="e.g. COMP 016" required>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" value="{{ old('title', $subject->title ?? '') }}" required>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-3">
        <label class="form-label">Year level</label>
        <input type="number" name="year_level" min="1" max="10" class="form-control" value="{{ old('year_level', $subject->year_level ?? '') }}" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Semester</label>
        <select name="semester" class="form-select" required>
            @foreach (['1st', '2nd', 'summer'] as $sem)
                <option value="{{ $sem }}" @selected(old('semester', $subject->semester ?? null) === $sem)>{{ $sem }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Prerequisite</label>
        <input type="text" name="prerequisite" class="form-control" value="{{ old('prerequisite', $subject->prerequisite ?? '') }}">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Co-requisite</label>
        <input type="text" name="corequisite" class="form-control" value="{{ old('corequisite', $subject->corequisite ?? '') }}">
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-3">
        <label class="form-label">Lecture hours</label>
        <input type="number" step="0.5" name="lecture_hours" class="form-control" value="{{ old('lecture_hours', $subject->lecture_hours ?? '') }}">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Lab hours</label>
        <input type="number" step="0.5" name="lab_hours" class="form-control" value="{{ old('lab_hours', $subject->lab_hours ?? '') }}">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Units</label>
        <input type="number" step="0.5" name="credited_units" class="form-control" value="{{ old('credited_units', $subject->credited_units ?? '') }}">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Tuition hours</label>
        <input type="number" step="0.5" name="tuition_hours" class="form-control" value="{{ old('tuition_hours', $subject->tuition_hours ?? '') }}">
    </div>
</div>
