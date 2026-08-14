<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Syllabus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Syllabus>
 */
class SyllabusFactory extends Factory
{
    protected $model = Syllabus::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'file_path' => 'syllabi/test/' . fake()->uuid() . '.pdf',
            'file_type' => 'pdf',
            'raw_text' => fake()->paragraph(),
            'curriculum_year' => '2025-2026',
            'status' => 'processed',
            'uploaded_by' => User::factory(),
        ];
    }
}
