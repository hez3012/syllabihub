@extends('layouts.app')

@section('title', 'Upload Syllabus')

@section('content')
    <a href="{{ route('courses.show', $course) }}" class="back-link">
        <i class="bi bi-arrow-left"></i> Back to {{ $course->course_code }}
    </a>

    <form method="POST" action="{{ route('syllabi.store', $course) }}" enctype="multipart/form-data" id="sh-upload-form">
        @csrf

        <div class="sh-upload-form-header">
            <div>
                <h1 class="sh-page-title" style="font-size: var(--text-2xl);">Upload Syllabus</h1>
                <p class="sh-upload-course-label">{{ $course->course_code }} — {{ $course->title }}</p>
            </div>
        </div>

        <div class="sh-upload-card">
            {{-- Curriculum Year --}}
            <div class="sh-upload-field">
                <label class="form-label">Curriculum Year</label>
                <select name="curriculum_year" class="form-select" style="max-width: 200px;" required>
                    <option value="">Select year</option>
                    @foreach ($curriculumYears as $year)
                        <option value="{{ $year }}" @selected(old('curriculum_year') === $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            {{-- PDF drop zone --}}
            <div class="sh-upload-zone" id="sh-drop-pdf" data-input="file_pdf">
                <input type="file" name="file_pdf" id="file_pdf" accept=".pdf" class="sh-upload-input">
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

            {{-- DOCX drop zone --}}
            <div class="sh-upload-zone" id="sh-drop-docx" data-input="file_docx">
                <input type="file" name="file_docx" id="file_docx" accept=".docx" class="sh-upload-input">
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

            <div class="sh-upload-actions">
                <a href="{{ route('courses.show', $course) }}" class="btn btn-pup-outline-dark">Cancel</a>
                <button type="submit" class="btn btn-pup-primary" id="sh-upload-submit" disabled>
                    <i class="bi bi-upload"></i> Upload Syllabus
                </button>
            </div>
        </div>
    </form>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var zones = document.querySelectorAll('.sh-upload-zone');
        var submitBtn = document.getElementById('sh-upload-submit');

        function formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        function updateSubmitState() {
            var inputs = document.querySelectorAll('.sh-upload-input');
            var hasFile = false;
            inputs.forEach(function (input) {
                if (input.files && input.files.length > 0) hasFile = true;
            });
            submitBtn.disabled = !hasFile;
        }

        function showFile(zone, file) {
            var content = zone.querySelector('.sh-upload-zone-content');
            var fileDiv = zone.querySelector('.sh-upload-zone-file');
            var nameEl = zone.querySelector('.sh-upload-file-name');
            var sizeEl = zone.querySelector('.sh-upload-file-size');

            content.classList.add('d-none');
            fileDiv.classList.remove('d-none');
            nameEl.textContent = file.name;
            sizeEl.textContent = formatSize(file.size);
        }

        function clearFile(zone) {
            var input = zone.querySelector('.sh-upload-input');
            var content = zone.querySelector('.sh-upload-zone-content');
            var fileDiv = zone.querySelector('.sh-upload-zone-file');

            input.value = '';
            content.classList.remove('d-none');
            fileDiv.classList.add('d-none');
            updateSubmitState();
        }

        zones.forEach(function (zone) {
            var input = zone.querySelector('.sh-upload-input');
            var removeBtn = zone.querySelector('.sh-upload-remove');

            // Click to browse
            zone.addEventListener('click', function (e) {
                if (e.target.closest('.sh-upload-remove')) return;
                input.click();
            });

            // File selected
            input.addEventListener('change', function () {
                if (input.files && input.files.length > 0) {
                    showFile(zone, input.files[0]);
                    updateSubmitState();
                }
            });

            // Remove button
            removeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                clearFile(zone);
            });

            // Drag events
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
                var files = e.dataTransfer.files;
                if (files.length > 0) {
                    // Check file type matches the zone
                    var accept = input.accept;
                    var fileName = files[0].name.toLowerCase();
                    if (accept && !fileName.endsWith(accept.replace('.', '.'))) {
                        return;
                    }
                    // Assign file to input via DataTransfer
                    var dt = new DataTransfer();
                    dt.items.add(files[0]);
                    input.files = dt.files;
                    showFile(zone, files[0]);
                    updateSubmitState();
                }
            });
        });
    });
    </script>
    @endpush
@endsection
