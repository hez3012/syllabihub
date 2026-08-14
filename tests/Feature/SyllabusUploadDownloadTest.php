<?php

namespace Tests\Feature;

use App\Http\Controllers\SyllabusController;
use App\Models\Course;
use App\Models\Syllabus;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyllabusUploadDownloadTest extends TestCase
{
    use DatabaseTransactions;

    private function realUploadedFile(string $fixtureName, string $originalName, string $mime): UploadedFile
    {
        return new UploadedFile(
            base_path("tests/Fixtures/{$fixtureName}"),
            $originalName,
            $mime,
            null,
            true // treat as an already-uploaded test file, skip is_uploaded_file() check
        );
    }

    public function test_authenticated_faculty_can_upload_a_valid_pdf_and_it_gets_processed(): void
    {
        Storage::fake('local');
        $faculty = User::factory()->create(['role' => 'faculty']);
        $course = Course::factory()->create();

        $file = $this->realUploadedFile('sample-syllabus.pdf', 'sample-syllabus.pdf', 'application/pdf');

        $response = $this->actingAs($faculty)->post("/courses/{$course->id}/syllabus", [
            'file_pdf' => $file,
            'curriculum_year' => SyllabusController::curriculumYearOptions()[0],
        ]);

        $response->assertRedirect(route('courses.show', $course));

        $syllabus = Syllabus::where('course_id', $course->id)->firstOrFail();
        $this->assertSame('processed', $syllabus->status);
        $this->assertSame('pdf', $syllabus->file_type);
        $this->assertSame('sample-syllabus.pdf', $syllabus->original_filename);
        $this->assertStringContainsString('Fixture Subject', $syllabus->raw_text);
        Storage::disk('local')->assertExists($syllabus->file_path);
    }

    public function test_valid_docx_upload_extracts_text(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();

        $file = $this->realUploadedFile(
            'sample-syllabus.docx',
            'sample-syllabus.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/syllabus", [
            'file_docx' => $file,
            'curriculum_year' => SyllabusController::curriculumYearOptions()[0],
        ]);

        $response->assertRedirect();
        $syllabus = Syllabus::where('course_id', $course->id)->firstOrFail();
        $this->assertSame('processed', $syllabus->status);
        $this->assertSame('docx', $syllabus->file_type);
        $this->assertStringContainsString('Fixture Subject', $syllabus->raw_text);
    }

    public function test_uploading_both_pdf_and_docx_together_creates_two_syllabus_rows(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/syllabus", [
            'file_pdf' => $this->realUploadedFile('sample-syllabus.pdf', 'sample-syllabus.pdf', 'application/pdf'),
            'file_docx' => $this->realUploadedFile(
                'sample-syllabus.docx',
                'sample-syllabus.docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ),
            'curriculum_year' => SyllabusController::curriculumYearOptions()[0],
        ]);

        $response->assertRedirect();
        $this->assertSame(2, Syllabus::where('course_id', $course->id)->count());
        $this->assertSame(1, Syllabus::where('course_id', $course->id)->where('file_type', 'pdf')->count());
        $this->assertSame(1, Syllabus::where('course_id', $course->id)->where('file_type', 'docx')->count());
    }

    public function test_reuploading_the_same_file_type_replaces_the_existing_syllabus_instead_of_appending(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();

        $this->actingAs($admin)->post("/courses/{$course->id}/syllabus", [
            'file_pdf' => $this->realUploadedFile('sample-syllabus.pdf', 'sample-syllabus.pdf', 'application/pdf'),
            'curriculum_year' => SyllabusController::curriculumYearOptions()[0],
        ]);
        $firstPdf = Syllabus::where('course_id', $course->id)->where('file_type', 'pdf')->firstOrFail();

        $this->actingAs($admin)->post("/courses/{$course->id}/syllabus", [
            'file_pdf' => $this->realUploadedFile('sample-syllabus.pdf', 'updated-syllabus.pdf', 'application/pdf'),
            'curriculum_year' => SyllabusController::curriculumYearOptions()[0],
        ]);

        // Old one is retired (soft-deleted), not left sitting alongside the new one.
        $this->assertSoftDeleted('syllabi', ['id' => $firstPdf->id]);
        $this->assertSame(1, Syllabus::where('course_id', $course->id)->where('file_type', 'pdf')->count());

        $currentPdf = Syllabus::where('course_id', $course->id)->where('file_type', 'pdf')->firstOrFail();
        $this->assertSame('updated-syllabus.pdf', $currentPdf->original_filename);

        // Uploading a PDF never touches an existing DOCX of the same course.
        $this->actingAs($admin)->post("/courses/{$course->id}/syllabus", [
            'file_docx' => $this->realUploadedFile(
                'sample-syllabus.docx',
                'sample-syllabus.docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ),
            'curriculum_year' => SyllabusController::curriculumYearOptions()[0],
        ]);
        $this->assertSame(1, Syllabus::where('course_id', $course->id)->where('file_type', 'pdf')->count());
        $this->assertSame(1, Syllabus::where('course_id', $course->id)->where('file_type', 'docx')->count());
    }

    public function test_upload_requires_at_least_one_file(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/syllabus", []);

        $response->assertSessionHasErrors('file_pdf');
        $this->assertSame(0, Syllabus::where('course_id', $course->id)->count());
    }

    public function test_upload_with_a_file_but_no_curriculum_year_is_rejected(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/syllabus", [
            'file_pdf' => $this->realUploadedFile('sample-syllabus.pdf', 'sample-syllabus.pdf', 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('curriculum_year');
        $this->assertSame(0, Syllabus::where('course_id', $course->id)->count());
    }

    public function test_upload_rejects_a_disallowed_file_type(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();

        $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/syllabus", [
            'file_pdf' => $file,
        ]);

        $response->assertSessionHasErrors('file_pdf');
        $this->assertSame(0, Syllabus::where('course_id', $course->id)->count());
    }

    public function test_structurally_broken_pdf_uploads_with_failed_status_but_stays_downloadable(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();

        // Real %PDF header (passes mimes:pdf validation) but not a parseable
        // PDF structure — exercises SyllabusTextExtractor's failure path.
        $file = $this->realUploadedFile('corrupted-syllabus.pdf', 'corrupted-syllabus.pdf', 'application/pdf');

        $response = $this->actingAs($admin)->post("/courses/{$course->id}/syllabus", [
            'file_pdf' => $file,
            'curriculum_year' => SyllabusController::curriculumYearOptions()[0],
        ]);

        $response->assertRedirect();
        $syllabus = Syllabus::where('course_id', $course->id)->firstOrFail();
        $this->assertSame('failed', $syllabus->status);
        $this->assertNull($syllabus->raw_text);

        $download = $this->actingAs($admin)->get(route('syllabi.download', $syllabus));
        $download->assertOk();
    }

    public function test_guest_is_redirected_away_from_the_upload_form(): void
    {
        $course = Course::factory()->create();

        $response = $this->get("/courses/{$course->id}/syllabus/upload");

        $response->assertRedirect(route('login'));
    }

    public function test_download_requires_authentication(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('syllabi/test/public-download.pdf', 'fake pdf bytes for download test');

        $syllabus = Syllabus::factory()->create([
            'file_path' => 'syllabi/test/public-download.pdf',
        ]);
        $user = User::factory()->create(['role' => 'faculty']);

        $response = $this->actingAs($user)->get(route('syllabi.download', $syllabus));

        $response->assertOk();
    }

    public function test_guest_is_redirected_away_from_download(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('syllabi/test/public-download.pdf', 'fake pdf bytes for download test');

        $syllabus = Syllabus::factory()->create([
            'file_path' => 'syllabi/test/public-download.pdf',
        ]);

        $response = $this->get(route('syllabi.download', $syllabus));

        $response->assertRedirect(route('login'));
    }

    public function test_download_returns_404_when_file_missing_from_disk(): void
    {
        Storage::fake('local');

        $syllabus = Syllabus::factory()->create([
            'file_path' => 'syllabi/test/does-not-exist.pdf',
        ]);
        $user = User::factory()->create(['role' => 'faculty']);

        $response = $this->actingAs($user)->get(route('syllabi.download', $syllabus));

        $response->assertNotFound();
    }

    public function test_preview_serves_pdf_inline(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('syllabi/test/preview.pdf', 'fake pdf bytes for preview test');

        $syllabus = Syllabus::factory()->create([
            'file_path' => 'syllabi/test/preview.pdf',
            'file_type' => 'pdf',
        ]);
        $user = User::factory()->create(['role' => 'faculty']);

        $response = $this->actingAs($user)->get(route('syllabi.preview', $syllabus));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringStartsWith('inline', $response->headers->get('content-disposition'));
    }

    public function test_preview_rejects_non_pdf(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('syllabi/test/preview.docx', 'fake docx bytes');

        $syllabus = Syllabus::factory()->create([
            'file_path' => 'syllabi/test/preview.docx',
            'file_type' => 'docx',
        ]);
        $user = User::factory()->create(['role' => 'faculty']);

        $response = $this->actingAs($user)->get(route('syllabi.preview', $syllabus));

        $response->assertStatus(415);
    }
}
