<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobRole;
use App\Models\User;
use App\Services\EmployeeAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveOrganisationRecord
{
    public function __construct(private EmployeeAccess $people) {}

    /** @param array{code: string, name: string, is_active: bool} $data */
    public function handle(User $actor, Department|JobRole $record, array $data): Department|JobRole
    {
        try {
            return DB::transaction(function () use ($actor, $record, $data): Department|JobRole {
                Gate::forUser($this->people->currentAccount($actor))->authorize('manage-directory');
                $graph = Employee::query()->orderBy('id')->lockForUpdate()->get(['id', 'department_id', 'job_role_id', 'is_active']);
                if ($record->exists) {
                    $record = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
                } else {
                    $record->code = $data['code'];
                }
                if (! $data['is_active'] && $record->exists && $graph->contains(fn (Employee $person): bool => $person->is_active && ($record instanceof Department ? $person->department_id : $person->job_role_id) === $record->id)) {
                    throw ValidationException::withMessages(['is_active' => 'Reassign active employees before retiring this record.']);
                }
                $record->forceFill(['name' => $data['name'], 'is_active' => $data['is_active']])->save();

                AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => 'directory.catalogue.saved', 'subject_type' => $record instanceof Department ? 'department' : 'job_role', 'subject_id' => $record->id, 'details' => ['code' => $record->code, 'is_active' => $record->is_active]]);

                return $record;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'This code is already in use.']);
        }
    }
}
