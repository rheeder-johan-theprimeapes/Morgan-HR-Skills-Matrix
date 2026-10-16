<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Skill;
use App\Models\SkillTarget;
use App\Services\EmployeeProfile;
use App\Services\TargetResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class EmployeeTargetController extends Controller
{
    public function show(Employee $employee, TargetResolver $resolver): View
    {
        Gate::authorize('view', $employee);
        $skills = Skill::query()->where('is_active', true)->with(['group', 'departments', 'jobRoles'])->orderBy('skill_group_id')->orderBy('name')->orderBy('id')->get();
        $targets = SkillTarget::query()->where('is_active', true)->where(function ($query) use ($employee): void {
            $query->where('employee_id', $employee->id);
            if ($employee->department_id !== null) {
                $query->orWhere('department_id', $employee->department_id);
            }
            if ($employee->job_role_id !== null) {
                $query->orWhere('job_role_id', $employee->job_role_id);
            }
        })->with('level')->get();
        $rows = [];
        foreach ($skills as $skill) {
            $resolved = $resolver->resolveFrom($employee, $skill, $targets);
            if ($resolved->applicable) {
                $rows[] = ['skill_id' => $skill->id, 'name' => $skill->name, 'group' => $skill->group?->name, 'target' => $resolved];
            }
        }

        return view('employees.targets', ['profile' => EmployeeProfile::fromEmployee($employee->load(['department', 'jobRole', 'manager'])), 'rows' => $rows]);
    }
}
