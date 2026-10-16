<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Skill;
use App\Models\SkillLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SkillLevel> */
class SkillLevelFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['skill_id' => Skill::factory(), 'ordinal' => 1, 'label' => 'Illustrative developing', 'guidance' => 'Demonstration guidance; not approved live policy.', 'is_active' => true];
    }
}
