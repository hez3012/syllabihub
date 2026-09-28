<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    /**
     * Legacy column attributes (`program_id`, `year_level`, `semester`) are
     * captured here on the way into create() and written to the
     * course_program pivot by the afterCreating hook below. Static because
     * Factory::create() re-enters itself on a cloned instance once the
     * remaining attributes are applied.
     */
    private static array $pendingPlacement = [];

    public function definition(): array
    {
        return [
            'course_code' => strtoupper(fake()->unique()->bothify('TST ###')),
            'title' => fake()->sentence(3),
            'lecture_hours' => 2,
            'lab_hours' => 3,
            'credited_units' => 3,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Course $course) {
            if ($course->programs()->exists()) {
                return;
            }

            $placement = self::$pendingPlacement;
            self::$pendingPlacement = [];

            $programId = $placement['program_id'] ?? null;
            if ($programId instanceof Model) {
                $programId = $programId->getKey();
            }
            $programId ??= Program::factory()->create()->getKey();

            $course->programs()->attach($programId, [
                'year_level' => $placement['year_level'] ?? fake()->numberBetween(1, 4),
                'semester' => $placement['semester'] ?? fake()->randomElement(['1st', '2nd', 'summer']),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        $placement = [];

        foreach (['program_id', 'year_level', 'semester'] as $key) {
            if (array_key_exists($key, $attributes)) {
                $placement[$key] = $attributes[$key];
                unset($attributes[$key]);
            }
        }

        if ($placement !== []) {
            self::$pendingPlacement = $placement;
        }

        return parent::create($attributes, $parent);
    }
}
