<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->unsignedTinyInteger('year_level')->nullable();
            $table->enum('semester', ['1st', '2nd', 'summer'])->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'program_id']);
            $table->index('program_id');
        });

        // Backfill one pivot row per existing course from its legacy columns.
        DB::table('courses')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(200, function ($courses) {
                $now = now();
                $rows = [];

                foreach ($courses as $course) {
                    if (! $course->program_id) {
                        continue;
                    }

                    $rows[] = [
                        'course_id' => $course->id,
                        'program_id' => $course->program_id,
                        'year_level' => $course->year_level,
                        'semester' => $course->semester,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows) {
                    DB::table('course_program')->insert($rows);
                }
            });

        // Legacy key/index names were created outside of migrations — and a
        // down()/up() round trip recreates the column without them, so only
        // drop what actually exists.
        $foreignKey = DB::selectOne(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses'
               AND COLUMN_NAME = 'program_id' AND REFERENCED_TABLE_NAME = 'programs'"
        );

        if ($foreignKey) {
            DB::statement('ALTER TABLE courses DROP FOREIGN KEY ' . $foreignKey->CONSTRAINT_NAME);
        }

        $legacyIndex = DB::selectOne(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses'
               AND COLUMN_NAME = 'program_id' AND INDEX_NAME <> 'PRIMARY'"
        );

        if ($legacyIndex) {
            DB::statement('DROP INDEX ' . $legacyIndex->INDEX_NAME . ' ON courses');
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['program_id', 'year_level', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('created_by');
            $table->unsignedTinyInteger('year_level')->nullable()->after('title');
            $table->enum('semester', ['1st', '2nd', 'summer'])->nullable()->after('year_level');
        });

        DB::table('course_program')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('courses')->where('id', $row->course_id)->update([
                    'program_id' => $row->program_id,
                    'year_level' => $row->year_level,
                    'semester' => $row->semester,
                ]);
            }
        });

        Schema::dropIfExists('course_program');
    }
};
