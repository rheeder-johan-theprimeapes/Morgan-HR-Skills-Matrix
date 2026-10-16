<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobRole;
use App\Models\Skill;
use App\Models\SkillGroup;
use App\Models\SkillLevel;
use App\Models\SkillTarget;
use App\Models\User;
use App\Services\EmployeeAccess;
use App\TargetScope;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveSkillTarget
{
    public function __construct(private EmployeeAccess $access) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return ['scope' => ['required', Rule::enum(TargetScope::class)], 'subject_id' => ['required', 'integer', 'min:1'], 'skill_level_id' => ['required', 'integer', 'exists:skill_levels,id'], 'reason' => ['required_if:scope,employee', 'nullable', 'string', 'max:1000'], 'is_active' => ['required', 'boolean']];
    }

    /** @param array<string, mixed> $data */
    public function handle(User $actor, Skill $skill, array $data): SkillTarget
    {
        try {
            return DB::transaction(function () use ($actor, $skill, $data): SkillTarget {
                $account = $this->access->currentAccount($actor);
                Gate::forUser($account)->authorize('manage-skills');
                if ($account === null) {
                    abort(403);
                }
                $values = Validator::make($data, self::rules())->validate();
                $record = Skill::query()->lockForUpdate()->findOrFail($skill->id);
                $group = SkillGroup::query()->whereKey($record->skill_group_id)->lockForUpdate()->firstOrFail();
                $level = SkillLevel::query()->whereKey($values['skill_level_id'])->lockForUpdate()->firstOrFail();
                if ($level->skill_id !== $record->id || ($values['is_active'] && (! $record->is_active || ! $group->is_active || ! $level->is_active))) {
                    throw ValidationException::withMessages(['skill_level_id' => 'Choose an active rating definition for this skill.']);
                }
                $scope = TargetScope::from($values['scope']);
                $column = match ($scope) {
                    TargetScope::Department => 'department_id', TargetScope::JobRole => 'job_role_id', TargetScope::Employee => 'employee_id'
                };
                $subject = match ($scope) {
                    TargetScope::Department => Department::query()->whereKey($values['subject_id'])->lockForUpdate()->first(),
                    TargetScope::JobRole => JobRole::query()->whereKey($values['subject_id'])->lockForUpdate()->first(),
                    TargetScope::Employee => Employee::query()->whereKey($values['subject_id'])->lockForUpdate()->first(),
                };
                if ($subject === null || ($values['is_active'] && ! $subject->is_active)) {
                    throw ValidationException::withMessages(['subject_id' => 'Choose an active record in this target scope.']);
                }
                if ($subject instanceof Employee && ! $this->access->canView($account, $subject)) {
                    abort(403);
                }
                $target = SkillTarget::query()->where('skill_id', $record->id)->where($column, $subject->id)->lockForUpdate()->first() ?? new SkillTarget;
                $target->fill(['skill_id' => $record->id, 'skill_level_id' => $level->id, 'department_id' => $scope === TargetScope::Department ? $subject->id : null, 'job_role_id' => $scope === TargetScope::JobRole ? $subject->id : null, 'employee_id' => $scope === TargetScope::Employee ? $subject->id : null, 'approved_by' => $account->id, 'reason' => $scope === TargetScope::Employee ? $values['reason'] : null, 'is_active' => $values['is_active']])->save();
                AuditEvent::query()->create(['actor_id' => $account->id, 'event' => 'catalogue.target.saved', 'subject_type' => 'skill_target', 'subject_id' => $target->id, 'details' => ['skill_id' => $record->id, 'skill_level_id' => $level->id, 'scope' => $scope->value, 'is_active' => $target->is_active]]);

                return $target;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['subject_id' => 'This target changed concurrently. Reload and try again.']);
        }
    }
}
