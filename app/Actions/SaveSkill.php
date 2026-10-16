<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\JobRole;
use App\Models\Skill;
use App\Models\SkillGroup;
use App\Models\User;
use App\Services\EmployeeAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveSkill
{
    public function __construct(private EmployeeAccess $people) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(?Skill $skill): array
    {
        return [
            'code' => $skill?->exists ? ['prohibited'] : ['required', 'string', 'max:40', 'regex:/\A[A-Z0-9][A-Z0-9._-]{0,39}\z/', Rule::unique('skills', 'code')],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:4000'],
            'skill_group_id' => ['required', 'integer', 'exists:skill_groups,id'],
            'allows_na' => ['required', 'boolean'], 'requires_evidence' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'],
            'department_ids' => ['sometimes', 'array', 'max:200'], 'department_ids.*' => ['integer', 'distinct', 'exists:departments,id'],
            'job_role_ids' => ['sometimes', 'array', 'max:200'], 'job_role_ids.*' => ['integer', 'distinct', 'exists:job_roles,id'],
        ];
    }

    /** @param array<string, mixed> $data */
    public function handle(User $actor, ?Skill $skill, array $data): Skill
    {
        try {
            return DB::transaction(function () use ($actor, $skill, $data): Skill {
                Gate::forUser($this->people->currentAccount($actor))->authorize('manage-skills');
                $values = Validator::make($data, self::rules($skill))->validate();
                $group = SkillGroup::query()->whereKey($values['skill_group_id'])->lockForUpdate()->firstOrFail();
                $record = $skill === null ? new Skill : Skill::query()->lockForUpdate()->findOrFail($skill->id);
                if (! $group->is_active && ($values['is_active'] || ! $record->exists || $record->skill_group_id !== $group->id)) {
                    throw ValidationException::withMessages(['skill_group_id' => 'Choose an active group.']);
                }
                $departments = $values['department_ids'] ?? [];
                $roles = $values['job_role_ids'] ?? [];
                $retainedDepartments = $record->exists ? $record->departments()->lockForUpdate()->pluck('departments.id')->all() : [];
                $departmentRecords = Department::query()->whereKey($departments)->orderBy('id')->lockForUpdate()->get(['id', 'is_active']);
                $validDepartments = $departmentRecords->filter(fn (Department $department): bool => $department->is_active || in_array($department->id, $retainedDepartments, true))->modelKeys();
                if (array_diff($departments, $validDepartments) !== []) {
                    throw ValidationException::withMessages(['department_ids' => 'Choose active departments.']);
                }
                $retainedRoles = $record->exists ? $record->jobRoles()->lockForUpdate()->pluck('job_roles.id')->all() : [];
                $roleRecords = JobRole::query()->whereKey($roles)->orderBy('id')->lockForUpdate()->get(['id', 'is_active']);
                $validRoles = $roleRecords->filter(fn (JobRole $role): bool => $role->is_active || in_array($role->id, $retainedRoles, true))->modelKeys();
                if (array_diff($roles, $validRoles) !== []) {
                    throw ValidationException::withMessages(['job_role_ids' => 'Choose active job roles.']);
                }
                unset($values['department_ids'], $values['job_role_ids']);
                $record->fill($values)->save();
                $record->departments()->sync($departments);
                $record->jobRoles()->sync($roles);
                AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => 'catalogue.skill.saved', 'subject_type' => 'skill', 'subject_id' => $record->id, 'details' => ['code' => $record->code, 'is_active' => $record->is_active]]);

                return $record;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'This skill code is already in use.']);
        }
    }
}
