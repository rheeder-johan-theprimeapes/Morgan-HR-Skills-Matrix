<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PdrSubmission;
use App\Models\PdrTransition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PdrTransition> */
class PdrTransitionFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['pdr_submission_id' => PdrSubmission::factory(), 'pdr_review_id' => fn (array $attributes): int => PdrSubmission::query()->whereKey($attributes['pdr_submission_id'])->firstOrFail()->pdr_review_id, 'head_version' => 2, 'action' => 'submitted', 'reason' => 'Illustrative test transition', 'actor_id' => User::factory()->withEmployee()];
    }
}
