<?php

namespace Tests\Feature;

use App\Models\Subject;
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
        $subject = Subject::factory()->create();

        $file = $this->realUploadedFile('sample-syllabus.pdf', 'sample-syllabus.pdf', 'application/pdf');

        $response = $this->actingAs($faculty)->post("/subjects/{$subject->id}/syllabus", [
            'file' => $file,
            'curriculum_year' => '2025-2026',
        ]);

        $response->assertRedirect(route('subjects.show', $subject));

        $syllabus = Syllabus::where('subject_id', $subject->id)->firstOrFail();
        $this->assertSame('processed', $syllabus->status);
        $this->assertStringContainsString('Fixture Subject', $syllabus->raw_text);
        Storage::disk('local')->assertExists($syllabus->file_path);
    }

    public function test_valid_docx_upload_extracts_text(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $subject = Subject::factory()->create();

        $file = $this->realUploadedFile(
            'sample-syllabus.docx',
            'sample-syllabus.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $response = $this->actingAs($admin)->post("/subjects/{$subject->id}/syllabus", [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $syllabus = Syllabus::where('subject_id', $subject->id)->firstOrFail();
        $this->assertSame('processed', $syllabus->status);
        $this->assertStringContainsString('Fixture Subject', $syllabus->raw_text);
    }

    public function test_upload_rejects_a_disallowed_file_type(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $subject = Subject::factory()->create();

        $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

        $response = $this->actingAs($admin)->post("/subjects/{$subject->id}/syllabus", [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, Syllabus::where('subject_id', $subject->id)->count());
    }

    public function test_structurally_broken_pdf_uploads_with_failed_status_but_stays_downloadable(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $subject = Subject::factory()->create();

        // Real %PDF header (passes mimes:pdf validation) but not a parseable
        // PDF structure — exercises SyllabusTextExtractor's failure path.
        $file = $this->realUploadedFile('corrupted-syllabus.pdf', 'corrupted-syllabus.pdf', 'application/pdf');

        $response = $this->actingAs($admin)->post("/subjects/{$subject->id}/syllabus", [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $syllabus = Syllabus::where('subject_id', $subject->id)->firstOrFail();
        $this->assertSame('failed', $syllabus->status);
        $this->assertNull($syllabus->raw_text);

        $download = $this->get(route('syllabi.download', $syllabus));
        $download->assertOk();
    }

    public function test_guest_is_redirected_away_from_the_upload_form(): void
    {
        $subject = Subject::factory()->create();

        $response = $this->get("/subjects/{$subject->id}/syllabus/upload");

        $response->assertRedirect(route('login'));
    }

    public function test_download_requires_no_authentication(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('syllabi/test/public-download.pdf', 'fake pdf bytes for download test');

        $syllabus = Syllabus::factory()->create([
            'file_path' => 'syllabi/test/public-download.pdf',
        ]);

        $response = $this->get(route('syllabi.download', $syllabus));

        $response->assertOk();
    }

    public function test_download_returns_404_when_file_missing_from_disk(): void
    {
        Storage::fake('local');

        $syllabus = Syllabus::factory()->create([
            'file_path' => 'syllabi/test/does-not-exist.pdf',
        ]);

        $response = $this->get(route('syllabi.download', $syllabus));

        $response->assertNotFound();
    }
}
