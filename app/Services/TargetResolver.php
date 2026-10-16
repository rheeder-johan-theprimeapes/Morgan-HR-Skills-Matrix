<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\Skill;
use App\Models\SkillTarget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TargetResolver
{
    public function __construct(private SkillApplicability $applicability) {}

    public function resolve(Employee $employee, Skill $skill): ResolvedTarget
    {
        $query = SkillTarget::query()->where('skill_id', $skill->id)->where('is_active', true)->orderBy('id');
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate()->with(['level' => fn ($levels) => $levels->lockForUpdate()]);
        } else {
            $query->with('level');
        }

        return $this->resolveFrom($employee, $skill, $query->get());
    }

    /** @param Collection<int, SkillTarget> $targets */
    public function resolveFrom(Employee $employee, Skill $skill, Collection $targets): ResolvedTarget
    {
        $skill->loadMissing(['group', 'departments', 'jobRoles']);
        if (! $skill->is_active || $skill->group?->is_active !== true) {
            return new ResolvedTarget(false, null, null, 'Skill is retired');
        }
        $eligible = $targets->filter(fn (SkillTarget $target): bool => $target->skill_id === $skill->id && $target->is_active);
        $exception = $eligible->first(fn (SkillTarget $target): bool => $target->employee_id === $employee->id);
        if ($exception !== null) {
            if (! $this->validLevel($exception, $skill)) {
                return new ResolvedTarget(true, null, null, 'Target needs HR attention: rating definition is retired or invalid', [$exception->id]);
            }

            return new ResolvedTarget(true, $exception->level?->ordinal, $exception->skill_level_id, 'HR-approved individual exception', [$exception->id], $exception->reason);
        }
        if (! $this->applicability->includes($employee, $skill)) {
            return new ResolvedTarget(false, null, null, 'Not applicable to this role or department');
        }
        $groups = $eligible->filter(fn (SkillTarget $target): bool => $target->employee_id === null && (($target->department_id !== null && $target->department_id === $employee->department_id) || ($target->job_role_id !== null && $target->job_role_id === $employee->job_role_id)));
        if ($groups->isEmpty()) {
            return new ResolvedTarget(true, null, null, 'No target');
        }
        foreach ($groups as $target) {
            if (! $this->validLevel($target, $skill)) {
                return new ResolvedTarget(true, null, null, 'Target needs HR attention: rating definition is retired or invalid', array_values($groups->map(fn (SkillTarget $target): int => $target->id)->all()));
            }
        }
        $ordered = $groups->sortByDesc(fn (SkillTarget $target): int => $target->level->ordinal ?? -1);
        $highest = $ordered->first();
        if ($highest === null) {
            return new ResolvedTarget(true, null, null, 'No target');
        }
        $winners = $ordered->filter(fn (SkillTarget $target): bool => $target->level?->ordinal === $highest->level?->ordinal)->sortBy('id');
        $source = $winners->count() > 1 ? 'Department and job role targets (equal)' : ($highest->department_id !== null ? 'Department target' : 'Job role target');

        return new ResolvedTarget(true, $highest->level?->ordinal, $highest->skill_level_id, $source, array_values($winners->map(fn (SkillTarget $target): int => $target->id)->all()));
    }

    private function validLevel(SkillTarget $target, Skill $skill): bool
    {
        return $target->level?->is_active === true && $target->level->skill_id === $skill->id;
    }
}
