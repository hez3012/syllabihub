<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    protected $model = Program::class;

    /**
     * `code` is unique and the real dev DB already has BSIT/DIT seeded
     * (visible to tests via DatabaseTransactions). Always generate a
     * throwaway random code here — never hardcode "BSIT"/"DIT", that would
     * collide with the real rows and fail the unique constraint.
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('PRG???')),
            'name' => fake()->sentence(4),
        ];
    }
}
