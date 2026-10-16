<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ScoringFramework;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScoringFramework> */
class ScoringFrameworkFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => 'Illustrative '.fake()->word(), 'illustrative' => true, 'revision' => 1, 'definition' => ['components' => [], 'bands' => []], 'created_by' => User::factory()->withEmployee()];
    }
}
