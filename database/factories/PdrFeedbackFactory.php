<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PdrDecision;
use App\Models\PdrFeedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PdrFeedback> */
class PdrFeedbackFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['pdr_decision_id' => PdrDecision::factory(), 'pdr_submission_id' => fn (array $attributes): int => PdrDecision::query()->whereKey($attributes['pdr_decision_id'])->firstOrFail()->pdr_submission_id, 'pdr_review_id' => fn (array $attributes): int => PdrDecision::query()->whereKey($attributes['pdr_decision_id'])->firstOrFail()->pdr_review_id, 'revision' => 1, 'held_on' => today()->toDateString(), 'summary' => 'Illustrative employee-intended feedback', 'development_message' => 'Fictional coaching support', 'recorded_by' => User::factory()->withEmployee()];
    }
}
