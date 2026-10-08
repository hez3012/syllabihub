<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('syllabi')) {
            return;
        }

        Schema::create('syllabi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses');
            $table->string('file_path', 500);
            $table->enum('file_type', ['pdf', 'docx']);
            $table->string('original_filename')->nullable();
            $table->longText('raw_text')->nullable();
            $table->string('curriculum_year', 20)->nullable();
            $table->enum('status', ['pending', 'processed', 'failed'])->nullable()->default('pending');
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->fullText('raw_text', 'ft_syllabus_search');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabi');
    }
};
