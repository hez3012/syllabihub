{{-- Drag-and-drop PDF/DOCX upload used by courses.create and courses.edit.
     NOT courses.request-edit (that form doesn't handle files).
     Expects $curriculumYears. --}}
<div class="sh-upload-field">
    <label class="form-label">Curriculum Year (required if uploading a file)</label>
    <select name="curriculum_year" class="form-select" style="max-width: 200px;">
        <option value="">Select year</option>
        @foreach ($curriculumYears as $year)
            <option value="{{ $year }}" @selected(old('curriculum_year') === $year)>{{ $year }}</option>
        @endforeach
    </select>
</div>

<div class="sh-upload-zone" data-upload-zone data-input="file_pdf">
    <input type="file" name="file_pdf" accept=".pdf" class="sh-upload-input">
    <div class="sh-upload-zone-content">
        <i class="bi bi-file-earmark-text"></i>
        <div class="sh-upload-zone-title">PDF Syllabus</div>
        <div class="sh-upload-zone-hint">Drop PDF here or <span class="sh-upload-browse">click to browse</span></div>
        <div class="sh-upload-zone-limit">Accepted: .pdf — Max: 200MB</div>
    </div>
    <div class="sh-upload-zone-file d-none">
        <div class="sh-upload-file-info">
            <i class="bi bi-file-earmark-text"></i>
            <div>
                <div class="sh-upload-file-name"></div>
                <div class="sh-upload-file-size"></div>
            </div>
        </div>
        <button type="button" class="sh-upload-remove" aria-label="Remove file">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
</div>
@error('file_pdf')
    <div class="sh-upload-error">{{ $message }}</div>
@enderror

<div class="sh-upload-zone" data-upload-zone data-input="file_docx">
    <input type="file" name="file_docx" accept=".docx" class="sh-upload-input">
    <div class="sh-upload-zone-content">
        <i class="bi bi-file-earmark-word"></i>
        <div class="sh-upload-zone-title">DOCX Syllabus</div>
        <div class="sh-upload-zone-hint">Drop DOCX here or <span class="sh-upload-browse">click to browse</span></div>
        <div class="sh-upload-zone-limit">Accepted: .docx — Max: 200MB</div>
    </div>
    <div class="sh-upload-zone-file d-none">
        <div class="sh-upload-file-info">
            <i class="bi bi-file-earmark-word"></i>
            <div>
                <div class="sh-upload-file-name"></div>
                <div class="sh-upload-file-size"></div>
            </div>
        </div>
        <button type="button" class="sh-upload-remove" aria-label="Remove file">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
</div>
@error('file_docx')
    <div class="sh-upload-error">{{ $message }}</div>
@enderror

<p class="sh-upload-note">At least one file is required. Uploading a new file replaces the existing file of the same type.</p>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-upload-zone]').forEach(function (zone) {
        if (zone.dataset.initialized) return;
        zone.dataset.initialized = 'true';

        var input = zone.querySelector('.sh-upload-input');
        var removeBtn = zone.querySelector('.sh-upload-remove');

        function formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        function showFile(file) {
            zone.querySelector('.sh-upload-zone-content').classList.add('d-none');
            var fileDiv = zone.querySelector('.sh-upload-zone-file');
            fileDiv.classList.remove('d-none');
            fileDiv.querySelector('.sh-upload-file-name').textContent = file.name;
            fileDiv.querySelector('.sh-upload-file-size').textContent = formatSize(file.size);
        }

        function clearFile() {
            input.value = '';
            zone.querySelector('.sh-upload-zone-content').classList.remove('d-none');
            zone.querySelector('.sh-upload-zone-file').classList.add('d-none');
        }

        zone.addEventListener('click', function (e) {
            if (e.target.closest('.sh-upload-remove')) return;
            input.click();
        });

        input.addEventListener('change', function () {
            if (input.files && input.files.length > 0) showFile(input.files[0]);
        });

        removeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            clearFile();
        });

        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            zone.classList.add('sh-upload-zone-dragover');
        });

        zone.addEventListener('dragleave', function () {
            zone.classList.remove('sh-upload-zone-dragover');
        });

        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('sh-upload-zone-dragover');
            if (e.dataTransfer.files.length > 0) {
                var dt = new DataTransfer();
                dt.items.add(e.dataTransfer.files[0]);
                input.files = dt.files;
                showFile(e.dataTransfer.files[0]);
            }
        });
    });
});
</script>
