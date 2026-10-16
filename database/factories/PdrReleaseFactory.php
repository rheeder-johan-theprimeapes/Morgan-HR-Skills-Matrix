<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PdrFeedback;
use App\Models\PdrRelease;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PdrRelease> */
class PdrReleaseFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['pdr_feedback_id' => PdrFeedback::factory(), 'pdr_submission_id' => fn (array $attributes): int => PdrFeedback::query()->whereKey($attributes['pdr_feedback_id'])->firstOrFail()->pdr_submission_id, 'pdr_review_id' => fn (array $attributes): int => PdrFeedback::query()->whereKey($attributes['pdr_feedback_id'])->firstOrFail()->pdr_review_id, 'summary' => ['schema_version' => 1, 'fixture' => 'Illustrative safe release'], 'released_by' => User::factory()->withEmployee()];
    }
}
