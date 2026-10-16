<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JobRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JobRole> */
class JobRoleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => fake()->unique()->numerify('ROLE-######'), 'name' => fake()->words(2, true), 'is_active' => true];
    }
}
