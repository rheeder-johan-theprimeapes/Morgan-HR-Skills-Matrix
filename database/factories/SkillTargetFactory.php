<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Skill;
use App\Models\SkillLevel;
use App\Models\SkillTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SkillTarget> */
class SkillTargetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'skill_id' => Skill::factory(), 'skill_level_id' => fn (array $attributes): int => SkillLevel::factory()->create(['skill_id' => $attributes['skill_id']])->id,
            'employee_id' => Employee::factory(), 'department_id' => null, 'job_role_id' => null,
            'approved_by' => User::factory()->withEmployee(), 'reason' => 'Illustrative HR-approved exception', 'is_active' => true,
        ];
    }
}
