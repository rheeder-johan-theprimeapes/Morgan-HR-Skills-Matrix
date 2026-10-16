<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ReviewCycle;
use App\Models\ScoringFramework;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReviewCycle> */
class ReviewCycleFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['code' => 'DEMO-'.fake()->unique()->numerify('CYCLE-#####'), 'name' => 'Illustrative review cycle', 'scoring_framework_id' => ScoringFramework::factory()->state(['published_at' => now()]), 'opens_on' => today()->subDay()->toDateString(), 'closes_on' => today()->addMonth()->toDateString(), 'requires_employee_input' => true, 'revision' => 1, 'created_by' => User::factory()->withEmployee()];
    }
}
