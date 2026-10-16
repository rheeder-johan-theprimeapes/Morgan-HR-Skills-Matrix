<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\Skill;
use App\Models\SkillLevel;
use App\Models\User;
use App\Services\EmployeeAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AddSkillLevel
{
    public function __construct(private EmployeeAccess $people) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(Skill $skill): array
    {
        return ['ordinal' => ['required', 'integer', 'between:0,100', Rule::unique('skill_levels', 'ordinal')->where('skill_id', $skill->id)], 'label' => ['required', 'string', 'max:100'], 'guidance' => ['required', 'string', 'max:2000']];
    }

    /** @param array<string, mixed> $data */
    public function handle(User $actor, Skill $skill, array $data): SkillLevel
    {
        try {
            return DB::transaction(function () use ($actor, $skill, $data): SkillLevel {
                Gate::forUser($this->people->currentAccount($actor))->authorize('manage-skills');
                $record = Skill::query()->lockForUpdate()->findOrFail($skill->id);
                if (! $record->is_active) {
                    throw ValidationException::withMessages(['ordinal' => 'Retired skills cannot receive new levels.']);
                }
                $values = Validator::make($data, self::rules($record))->validate();
                $level = $record->levels()->create($values);
                AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => 'catalogue.level.created', 'subject_type' => 'skill_level', 'subject_id' => $level->id, 'details' => ['skill_id' => $record->id, 'ordinal' => $level->ordinal]]);

                return $level;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['ordinal' => 'This level already exists for the skill.']);
        }
    }

    public function retire(User $actor, SkillLevel $level): void
    {
        DB::transaction(function () use ($actor, $level): void {
            Gate::forUser($this->people->currentAccount($actor))->authorize('manage-skills');
            $record = SkillLevel::query()->lockForUpdate()->findOrFail($level->id);
            $record->is_active = false;
            $record->save();
            AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => 'catalogue.level.retired', 'subject_type' => 'skill_level', 'subject_id' => $record->id, 'details' => []]);
        }, 3);
    }
}
