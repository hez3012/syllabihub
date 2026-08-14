{{-- Optional inline PDF/DOCX upload used by courses.create and
     courses.edit (NOT courses.request-edit — that form doesn't handle
     files, it only proposes field changes for approval). Uploading here
     replaces this course's existing syllabus of the same file type
     rather than adding alongside it. Expects $curriculumYears. --}}
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">PDF File (optional)</label>
        <input type="file" name="file_pdf" class="form-control" accept=".pdf">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">DOCX File (optional)</label>
        <input type="file" name="file_docx" class="form-control" accept=".docx">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Curriculum Year (required if uploading a file)</label>
        <select name="curriculum_year" class="form-select">
            <option value="">-- Select --</option>
            @foreach ($curriculumYears as $year)
                <option value="{{ $year }}">{{ $year }}</option>
            @endforeach
        </select>
    </div>
</div>
