<?php

namespace Database\Factories;

use App\Models\Program;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'subject_code' => strtoupper(fake()->unique()->bothify('TST ###')),
            'title' => fake()->sentence(3),
            'year_level' => fake()->numberBetween(1, 4),
            'semester' => fake()->randomElement(['1st', '2nd', 'summer']),
            'lecture_hours' => 2,
            'lab_hours' => 3,
            'credited_units' => 3,
        ];
    }
}
