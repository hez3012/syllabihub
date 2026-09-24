<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_trails', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('action'); // created, updated, deleted
            $table->string('subject_type'); // e.g. App\Models\Course
            $table->unsignedBigInteger('subject_id');
            $table->string('description')->nullable(); // human-readable summary
            $table->json('old_values')->nullable(); // previous field values
            $table->json('new_values')->nullable(); // new field values
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_trails');
    }
};
