<?php

declare(strict_types=1);

namespace Database\Factories;

use App\AssessmentState;
use App\Models\Assessment;
use App\Models\AssessmentRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssessmentRevision> */
class AssessmentRevisionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['assessment_id' => Assessment::factory(), 'revision' => 1, 'actor_id' => User::factory()->withEmployee(), 'assessed_on' => now()->toDateString(), 'state' => AssessmentState::Unassessed, 'skill_level_id' => null, 'comment' => 'Illustrative assessment', 'evidence_notes' => null, 'snapshot' => ['skill_name' => 'Illustrative skill', 'ordinal' => null]];
    }
}
