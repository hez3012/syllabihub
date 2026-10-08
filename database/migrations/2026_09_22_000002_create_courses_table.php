<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('courses')) {
            return;
        }

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('course_code', 20);
            $table->string('title');
            $table->string('prerequisite')->nullable();
            $table->string('corequisite')->nullable();
            $table->decimal('lecture_hours', 4, 1)->nullable();
            $table->decimal('lab_hours', 4, 1)->nullable();
            $table->decimal('credited_units', 3, 1)->nullable();
            $table->decimal('tuition_hours', 4, 1)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Generated columns so uniqueness only applies to non-deleted rows
            $table->string('title_active')->nullable()
                ->virtualAs('case when deleted_at is null then title end');
            $table->string('course_code_active', 20)->nullable()
                ->virtualAs('case when deleted_at is null then course_code end');

            $table->unique('title_active', 'uq_title_active');
            $table->unique('course_code_active', 'uq_course_code_active');
            $table->fullText(['course_code', 'title'], 'ft_course_search');

            $table->foreign('created_by', 'fk_courses_created_by')
                ->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
