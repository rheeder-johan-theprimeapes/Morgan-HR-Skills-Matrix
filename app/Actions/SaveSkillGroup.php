<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\SkillGroup;
use App\Models\User;
use App\Services\EmployeeAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveSkillGroup
{
    public function __construct(private EmployeeAccess $people) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(?SkillGroup $group): array
    {
        return [
            'code' => $group?->exists ? ['prohibited'] : ['required', 'string', 'max:40', 'regex:/\A[A-Z0-9][A-Z0-9._-]{0,39}\z/', Rule::unique('skill_groups', 'code')],
            'name' => ['required', 'string', 'max:255'], 'display_order' => ['required', 'integer', 'between:0,1000'], 'is_active' => ['required', 'boolean'],
        ];
    }

    /** @param array<string, mixed> $data */
    public function handle(User $actor, ?SkillGroup $group, array $data): SkillGroup
    {
        try {
            return DB::transaction(function () use ($actor, $group, $data): SkillGroup {
                Gate::forUser($this->people->currentAccount($actor))->authorize('manage-skills');
                $record = $group === null ? new SkillGroup : SkillGroup::query()->lockForUpdate()->findOrFail($group->id);
                $data += ['display_order' => 0];
                $values = Validator::make($data, self::rules($group))->validate();
                if (! $values['is_active'] && $record->exists && $record->skills()->where('is_active', true)->lockForUpdate()->first(['id']) !== null) {
                    throw ValidationException::withMessages(['is_active' => 'Retire or reassign active skills before retiring this group.']);
                }
                $record->fill($values)->save();
                AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => 'catalogue.group.saved', 'subject_type' => 'skill_group', 'subject_id' => $record->id, 'details' => ['is_active' => $record->is_active]]);

                return $record;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'This group code is already in use.']);
        }
    }
}
