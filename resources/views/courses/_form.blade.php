{{-- Shared fields for courses.create / courses.edit / courses.request-edit.
     Expects $programs and, when editing, $course (null on create).

     Programs are many-to-many: one course can live in both BSIT and DIT,
     each with its own year level / semester (course_program pivot). --}}
@php
    $selectedProgramIds = collect(old('program_ids'))
        ->whenEmpty(fn () => $course ? $course->programs->pluck('id') : collect())
        ->map(fn ($id) => (int) $id);

    $pivotByProgram = [];
    if ($course) {
        foreach ($course->programs as $program) {
            $pivotByProgram[$program->id] = [
                'year_level' => (string) ($program->pivot->year_level ?? ''),
                'semester' => $program->pivot->semester ?? '',
            ];
        }
    }
    $oldPlacements = old('year_level', []);
    $oldSemesters = old('semester', []);

    $yearOptions = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'];
    $semesterOptions = ['1st', '2nd', 'summer'];
@endphp

{{-- 1 — Program & placement --}}
<fieldset class="sh-form-section">
    <legend class="sh-form-section-head">
        <span class="sh-form-section-badge">1</span>
        <span class="sh-form-section-titles">
            <span class="sh-form-section-title">Program &amp; Placement</span>
            <span class="sh-form-section-hint">Tick every program that offers this subject, then set its year level and semester.</span>
        </span>
    </legend>
    <div class="sh-form-section-body">
        <div class="sh-upload-field">
            <label class="form-label" for="sh-program-first">Program <span class="sh-label-req">*</span></label>
            <div class="sh-program-checks">
                @foreach ($programs as $program)
                    <div class="form-check">
                        <input
                            type="checkbox"
                            name="program_ids[]"
                            value="{{ $program->id }}"
                            id="sh-program-{{ $program->id }}"
                            class="form-check-input sh-program-check"
                            data-program="{{ $program->id }}"
                            @checked($selectedProgramIds->contains($program->id))
                        >
                        <label class="form-check-label" for="sh-program-{{ $program->id }}">
                            <strong>{{ $program->code }}</strong> — {{ $program->name }}
                        </label>
                    </div>
                @endforeach
            </div>
            @error('program_ids')
                <div class="sh-field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="sh-upload-field">
            <label class="form-label">Year level &amp; semester <span class="sh-label-req">*</span></label>
            <div class="sh-placement-rows">
                @foreach ($programs as $program)
                    @php
                        $placementId = (string) $program->id;
                        $yearValue = $oldPlacements[$placementId]
                            ?? $pivotByProgram[$program->id]['year_level']
                            ?? '';
                        $semesterValue = $oldSemesters[$placementId]
                            ?? $pivotByProgram[$program->id]['semester']
                            ?? '';
                    @endphp
                    <div class="sh-placement-row" data-program-row="{{ $program->id }}" @unless($selectedProgramIds->contains($program->id)) style="display: none;" @endunless>
                        <div class="sh-placement-name">
                            <span class="program-pill {{ $program->code === 'DIT' ? 'program-pill-dit' : 'program-pill-bsit' }}">{{ $program->code }}</span>
                        </div>
                        <div class="sh-placement-field">
                            <label class="form-label" for="sh-year-{{ $program->id }}">Year Level</label>
                            <select name="year_level[{{ $program->id }}]" id="sh-year-{{ $program->id }}" class="form-select @error("year_level.$program->id") is-invalid @enderror">
                                <option value="">-- Select --</option>
                                @foreach ($yearOptions as $value => $label)
                                    <option value="{{ $value }}" @selected((string) $yearValue === (string) $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error("year_level.$program->id")
                                <div class="sh-field-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="sh-placement-field">
                            <label class="form-label" for="sh-sem-{{ $program->id }}">Semester</label>
                            <select name="semester[{{ $program->id }}]" id="sh-sem-{{ $program->id }}" class="form-select @error("semester.$program->id") is-invalid @enderror">
                                <option value="">-- Select --</option>
                                @foreach ($semesterOptions as $sem)
                                    <option value="{{ $sem }}" @selected($semesterValue === $sem)>{{ ucfirst($sem) }}</option>
                                @endforeach
                            </select>
                            @error("semester.$program->id")
                                <div class="sh-field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="sh-placement-empty sh-field-hint" @unless($selectedProgramIds->isEmpty()) style="display: none;" @endunless>
                Select at least one program to set its year level and semester.
            </div>
        </div>
    </div>
</fieldset>

{{-- 2 — Course identity --}}
<fieldset class="sh-form-section">
    <legend class="sh-form-section-head">
        <span class="sh-form-section-badge">2</span>
        <span class="sh-form-section-titles">
            <span class="sh-form-section-title">Course Identity</span>
            <span class="sh-form-section-hint">How this subject is written on the curriculum.</span>
        </span>
    </legend>
    <div class="sh-form-section-body">
        <div class="sh-form-grid sh-form-grid-code">
            <div class="sh-upload-field">
                <label class="form-label" for="sh-course-code">Course Code <span class="sh-label-req">*</span></label>
                <input type="text" id="sh-course-code" name="course_code" class="form-control @error('course_code') is-invalid @enderror" value="{{ old('course_code', $course->course_code ?? '') }}" placeholder="e.g., COMP 016">
                @error('course_code')
                    <div class="sh-field-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="sh-upload-field">
                <label class="form-label" for="sh-title">Title <span class="sh-label-req">*</span></label>
                <input type="text" id="sh-title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $course->title ?? '') }}" placeholder="e.g., Data Structures">
                @error('title')
                    <div class="sh-field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</fieldset>

{{-- 3 — Requirements --}}
<fieldset class="sh-form-section">
    <legend class="sh-form-section-head">
        <span class="sh-form-section-badge">3</span>
        <span class="sh-form-section-titles">
            <span class="sh-form-section-title">Requirements</span>
            <span class="sh-form-section-hint">Subjects that must be taken before or alongside this one.</span>
        </span>
    </legend>
    <div class="sh-form-section-body">
        <div class="sh-form-grid sh-form-grid-2">
            <div class="sh-upload-field">
                <label class="form-label" for="sh-prerequisite">Prerequisite <span class="sh-label-chip">Optional</span></label>
                <input type="text" id="sh-prerequisite" name="prerequisite" class="form-control @error('prerequisite') is-invalid @enderror" value="{{ old('prerequisite', $course->prerequisite ?? '') }}" placeholder="e.g., COMP 010">
                @error('prerequisite')
                    <div class="sh-field-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="sh-upload-field">
                <label class="form-label" for="sh-corequisite">Co-requisite <span class="sh-label-chip">Optional</span></label>
                <input type="text" id="sh-corequisite" name="corequisite" class="form-control @error('corequisite') is-invalid @enderror" value="{{ old('corequisite', $course->corequisite ?? '') }}" placeholder="e.g., COMP 017">
                @error('corequisite')
                    <div class="sh-field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</fieldset>

{{-- 4 — Units & hours --}}
<fieldset class="sh-form-section">
    <legend class="sh-form-section-head">
        <span class="sh-form-section-badge">4</span>
        <span class="sh-form-section-titles">
            <span class="sh-form-section-title">Units &amp; Hours</span>
            <span class="sh-form-section-hint">Weekly load for this subject.</span>
        </span>
    </legend>
    <div class="sh-form-section-body">
        <div class="sh-form-grid sh-form-grid-4">
            <div class="sh-upload-field">
                <label class="form-label" for="sh-lecture">Lecture Hours</label>
                <input type="number" step="0.5" min="0" id="sh-lecture" name="lecture_hours" class="form-control @error('lecture_hours') is-invalid @enderror" value="{{ old('lecture_hours', $course->lecture_hours ?? '') }}" placeholder="0">
                <span class="sh-unit">hrs / week</span>
                @error('lecture_hours')
                    <div class="sh-field-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="sh-upload-field">
                <label class="form-label" for="sh-lab">Lab Hours</label>
                <input type="number" step="0.5" min="0" id="sh-lab" name="lab_hours" class="form-control @error('lab_hours') is-invalid @enderror" value="{{ old('lab_hours', $course->lab_hours ?? '') }}" placeholder="0">
                <span class="sh-unit">hrs / week</span>
                @error('lab_hours')
                    <div class="sh-field-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="sh-upload-field">
                <label class="form-label" for="sh-units">Credited Units</label>
                <input type="number" step="0.5" min="0" id="sh-units" name="credited_units" class="form-control @error('credited_units') is-invalid @enderror" value="{{ old('credited_units', $course->credited_units ?? '') }}" placeholder="0">
                <span class="sh-unit">units</span>
                @error('credited_units')
                    <div class="sh-field-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="sh-upload-field">
                <label class="form-label" for="sh-tuition">Tuition Hours</label>
                <input type="number" step="0.5" min="0" id="sh-tuition" name="tuition_hours" class="form-control @error('tuition_hours') is-invalid @enderror" value="{{ old('tuition_hours', $course->tuition_hours ?? '') }}" placeholder="0">
                <span class="sh-unit">hrs / week</span>
                @error('tuition_hours')
                    <div class="sh-field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</fieldset>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var checks = Array.prototype.slice.call(document.querySelectorAll('.sh-program-check'));
    var empty = document.querySelector('.sh-placement-empty');
    if (!checks.length) return;

    function syncPlacements() {
        var anyChecked = false;

        checks.forEach(function (check) {
            var row = document.querySelector('[data-program-row="' + check.dataset.program + '"]');
            if (!row) return;

            row.style.display = check.checked ? '' : 'none';
            row.classList.toggle('is-selected', check.checked);

            if (check.checked) anyChecked = true;

            row.querySelectorAll('select').forEach(function (select) {
                select.disabled = !check.checked;
            });
        });

        if (empty) empty.style.display = anyChecked ? 'none' : '';
    }

    checks.forEach(function (check) {
        check.addEventListener('change', syncPlacements);
    });

    syncPlacements();
});
</script>
