<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Skill;
use App\Models\SkillGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Skill> */
class SkillFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => 'SKILL-'.fake()->unique()->numerify('######'), 'name' => 'Illustrative '.fake()->word().' '.fake()->word(), 'description' => 'Demonstration definition; HR approval required for live use.', 'skill_group_id' => SkillGroup::factory(), 'allows_na' => false, 'requires_evidence' => false, 'is_active' => true];
    }
}
