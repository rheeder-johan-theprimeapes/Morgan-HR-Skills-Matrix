<?php

declare(strict_types=1);

namespace Database\Factories;

use App\AssessmentKind;
use App\Models\Assessment;
use App\Models\Employee;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Assessment> */
class AssessmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['employee_id' => Employee::factory(), 'skill_id' => Skill::factory(), 'kind' => AssessmentKind::Self, 'revision' => 0, 'current_revision_id' => null];
    }
}
