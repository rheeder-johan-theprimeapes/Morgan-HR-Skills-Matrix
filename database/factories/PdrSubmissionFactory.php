<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PdrDraft;
use App\Models\PdrSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PdrSubmission> */
class PdrSubmissionFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['pdr_draft_id' => PdrDraft::factory(), 'pdr_review_id' => fn (array $attributes): int => PdrDraft::query()->whereKey($attributes['pdr_draft_id'])->firstOrFail()->pdr_review_id, 'submission_number' => 1, 'schema_version' => 1, 'snapshot' => ['schema_version' => 1, 'fixture' => 'Illustrative test submission'], 'submitted_by' => User::factory()->withEmployee()];
    }
}
