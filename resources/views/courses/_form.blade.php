{{-- Shared fields for courses.create / courses.edit / courses.request-edit.
     Expects $programs and, when editing, $course (null on create). --}}
<div class="sh-upload-field">
    <label class="form-label">Program</label>
    <select name="program_id" class="form-select @error('program_id') is-invalid @enderror">
        <option value="">-- Select --</option>
        @foreach ($programs as $program)
            <option value="{{ $program->id }}" @selected(old('program_id', $course->program_id ?? null) == $program->id)>
                {{ $program->code }} — {{ $program->name }}
            </option>
        @endforeach
    </select>
    @error('program_id')
        <div class="sh-field-error">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-6">
        <div class="sh-upload-field">
            <label class="form-label">Course Code</label>
            <input type="text" name="course_code" class="form-control @error('course_code') is-invalid @enderror" value="{{ old('course_code', $course->course_code ?? '') }}" placeholder="e.g., COMP 016">
            @error('course_code')
                <div class="sh-field-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="sh-upload-field">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $course->title ?? '') }}" placeholder="e.g., Data Structures">
            @error('title')
                <div class="sh-field-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="sh-upload-field">
            <label class="form-label">Year Level</label>
            <select name="year_level" class="form-select @error('year_level') is-invalid @enderror">
                <option value="">-- Select --</option>
                @foreach ([1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'] as $value => $label)
                    <option value="{{ $value }}" @selected((string) old('year_level', $course->year_level ?? '') === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('year_level')
                <div class="sh-field-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="sh-upload-field">
            <label class="form-label">Semester</label>
            <select name="semester" class="form-select @error('semester') is-invalid @enderror">
                <option value="">-- Select --</option>
                @foreach (['1st', '2nd', 'summer'] as $sem)
                    <option value="{{ $sem }}" @selected(old('semester', $course->semester ?? null) === $sem)>{{ ucfirst($sem) }}</option>
                @endforeach
            </select>
            @error('semester')
                <div class="sh-field-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="sh-upload-field">
            <label class="form-label">Prerequisite</label>
            <input type="text" name="prerequisite" class="form-control @error('prerequisite') is-invalid @enderror" value="{{ old('prerequisite', $course->prerequisite ?? '') }}" placeholder="Optional">
            @error('prerequisite')
                <div class="sh-field-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="sh-upload-field">
            <label class="form-label">Co-requisite</label>
            <input type="text" name="corequisite" class="form-control @error('corequisite') is-invalid @enderror" value="{{ old('corequisite', $course->corequisite ?? '') }}" placeholder="Optional">
            @error('corequisite')
                <div class="sh-field-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="sh-upload-field">
            <label class="form-label">Lecture Hours</label>
            <input type="number" step="0.5" name="lecture_hours" class="form-control @error('lecture_hours') is-invalid @enderror" value="{{ old('lecture_hours', $course->lecture_hours ?? '') }}">
            @error('lecture_hours')
                <div class="sh-field-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="sh-upload-field">
            <label class="form-label">Lab Hours</label>
            <input type="number" step="0.5" name="lab_hours" class="form-control @error('lab_hours') is-invalid @enderror" value="{{ old('lab_hours', $course->lab_hours ?? '') }}">
            @error('lab_hours')
                <div class="sh-field-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="sh-upload-field">
            <label class="form-label">Credited Units</label>
            <input type="number" step="0.5" name="credited_units" class="form-control @error('credited_units') is-invalid @enderror" value="{{ old('credited_units', $course->credited_units ?? '') }}">
            @error('credited_units')
                <div class="sh-field-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="sh-upload-field">
            <label class="form-label">Tuition Hours</label>
            <input type="number" step="0.5" name="tuition_hours" class="form-control @error('tuition_hours') is-invalid @enderror" value="{{ old('tuition_hours', $course->tuition_hours ?? '') }}">
            @error('tuition_hours')
                <div class="sh-field-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>
