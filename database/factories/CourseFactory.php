<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'course_code' => strtoupper(fake()->unique()->bothify('TST ###')),
            'title' => fake()->sentence(3),
            'year_level' => fake()->numberBetween(1, 4),
            'semester' => fake()->randomElement(['1st', '2nd', 'summer']),
            'lecture_hours' => 2,
            'lab_hours' => 3,
            'credited_units' => 3,
        ];
    }
}
