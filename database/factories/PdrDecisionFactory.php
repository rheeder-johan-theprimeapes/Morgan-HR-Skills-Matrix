<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PdrDecision;
use App\Models\PdrSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PdrDecision> */
class PdrDecisionFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['pdr_submission_id' => PdrSubmission::factory(), 'pdr_review_id' => fn (array $attributes): int => PdrSubmission::query()->whereKey($attributes['pdr_submission_id'])->firstOrFail()->pdr_review_id, 'actor_id' => User::factory()->withEmployee(), 'outcome' => 'approve', 'reason' => 'Illustrative human test decision', 'approved_pay' => ['outcome' => 'no_change']];
    }
}
