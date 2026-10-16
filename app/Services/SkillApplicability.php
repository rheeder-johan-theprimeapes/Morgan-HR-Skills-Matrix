<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\Skill;

class SkillApplicability
{
    public function includes(Employee $employee, Skill $skill): bool
    {
        $skill->loadMissing(['group', 'departments', 'jobRoles']);
        if (! $skill->is_active || $skill->group?->is_active !== true) {
            return false;
        }
        if ($skill->departments->isEmpty() && $skill->jobRoles->isEmpty()) {
            return true;
        }

        return $skill->departments->contains('id', $employee->department_id) || $skill->jobRoles->contains('id', $employee->job_role_id);
    }
}
