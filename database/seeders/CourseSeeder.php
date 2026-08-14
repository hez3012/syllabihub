<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Program;
use Illuminate\Database\Seeder;

/**
 * DEV/TEST DATA ONLY.
 *
 * These courses are placeholder entries used to exercise the fuzzy search
 * endpoint (SearchController) during backend development. They are NOT the
 * official BSIT/DIT curriculum — the real import from the curriculum
 * spreadsheets is owned by the database team (Mary, Vincent). Safe to
 * truncate/replace once real data lands.
 *
 * Run standalone so it doesn't collide with DatabaseSeeder's User::factory
 * call: php artisan db:seed --class=CourseSeeder
 */
class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $bsit = Program::where('code', 'BSIT')->first();
        $dit = Program::where('code', 'DIT')->first();

        if (!$bsit || !$dit) {
            $this->command?->error('Programs table missing BSIT/DIT rows — run the programs seed first.');
            return;
        }

        $courses = [
            // --- BSIT ---
            ['program_id' => $bsit->id, 'course_code' => 'COMP 001', 'title' => 'Introduction to Computing', 'year_level' => 1, 'semester' => '1st', 'lecture_hours' => 2, 'lab_hours' => 3, 'credited_units' => 3],
            ['program_id' => $bsit->id, 'course_code' => 'COMP 002', 'title' => 'Fundamentals of Programming', 'year_level' => 1, 'semester' => '1st', 'lecture_hours' => 2, 'lab_hours' => 3, 'credited_units' => 3],
            ['program_id' => $bsit->id, 'course_code' => 'COMP 003', 'title' => 'Intermediate Programming', 'year_level' => 1, 'semester' => '2nd', 'lecture_hours' => 2, 'lab_hours' => 3, 'credited_units' => 3],
            ['program_id' => $bsit->id, 'course_code' => 'COMP 008', 'title' => 'Data Structures and Algorithms', 'year_level' => 2, 'semester' => '1st', 'lecture_hours' => 2, 'lab_hours' => 3, 'credited_units' => 3],
            ['program_id' => $bsit->id, 'course_code' => 'COMP 011', 'title' => 'Object Oriented Programming', 'year_level' => 2, 'semester' => '1st', 'lecture_hours' => 2, 'lab_hours' => 3, 'credited_units' => 3],
            ['program_id' => $bsit->id, 'course_code' => 'COMP 016', 'title' => 'Web Development', 'year_level' => 2, 'semester' => '2nd', 'lecture_hours' => 2, 'lab_hours' => 3, 'credited_units' => 3],
            ['program_id' => $bsit->id, 'course_code' => 'COMP 020', 'title' => 'Database Management Systems', 'year_level' => 2, 'semester' => '2nd', 'lecture_hours' => 2, 'lab_hours' => 3, 'credited_units' => 3],
            ['program_id' => $bsit->id, 'course_code' => 'COMP 025', 'title' => 'Information Management', 'year_level' => 3, 'semester' => '1st', 'lecture_hours' => 3, 'lab_hours' => 0, 'credited_units' => 3],
            ['program_id' => $bsit->id, 'course_code' => 'COMP 030', 'title' => 'Systems Integration and Architecture', 'year_level' => 3, 'semester' => '1st', 'lecture_hours' => 2, 'lab_hours' => 3, 'credited_units' => 3],
            ['program_id' => $bsit->id, 'course_code' => 'COMP 035', 'title' => 'Networking 1', 'year_level' => 3, 'semester' => '2nd', 'lecture_hours' => 2, 'lab_hours' => 3, 'credited_units' => 3],
            ['program_id' => $bsit->id, 'course_code' => 'COMP 040', 'title' => 'Software Engineering', 'year_level' => 3, 'semester' => '2nd', 'lecture_hours' => 3, 'lab_hours' => 0, 'credited_units' => 3],
            ['program_id' => $bsit->id, 'course_code' => 'COMP 045', 'title' => 'Capstone Project 1', 'year_level' => 4, 'semester' => '1st', 'lecture_hours' => 0, 'lab_hours' => 3, 'credited_units' => 3],
            ['program_id' => $bsit->id, 'course_code' => 'COMP 050', 'title' => 'Capstone Project 2', 'year_level' => 4, 'semester' => '2nd', 'lecture_hours' => 0, 'lab_hours' => 3, 'credited_units' => 3],

            // --- DIT ---
            ['program_id' => $dit->id, 'course_code' => 'DIT 101', 'title' => 'Advanced Database Systems', 'year_level' => 1, 'semester' => '1st', 'lecture_hours' => 3, 'lab_hours' => 0, 'credited_units' => 3],
            ['program_id' => $dit->id, 'course_code' => 'DIT 102', 'title' => 'Data Mining and Analytics', 'year_level' => 1, 'semester' => '2nd', 'lecture_hours' => 3, 'lab_hours' => 0, 'credited_units' => 3],
            ['program_id' => $dit->id, 'course_code' => 'DIT 103', 'title' => 'Advanced Web and Mobile Development', 'year_level' => 2, 'semester' => '1st', 'lecture_hours' => 2, 'lab_hours' => 3, 'credited_units' => 3],
        ];

        foreach ($courses as $course) {
            Course::updateOrCreate(
                ['program_id' => $course['program_id'], 'course_code' => $course['course_code']],
                $course
            );
        }

        $this->command?->info('Seeded ' . count($courses) . ' test courses.');
    }
}
