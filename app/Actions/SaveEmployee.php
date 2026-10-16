<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobRole;
use App\Models\User;
use App\Services\EmployeeAccess;
use App\Services\ReportingTree;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveEmployee
{
    public function __construct(private ReportingTree $tree, private EmployeeAccess $people) {}

    /** @param array{employee_number: string, name: string, department_id: int|null, job_role_id: int|null, manager_id: int|null, is_active: bool} $data */
    public function handle(User $actor, ?Employee $employee, array $data): Employee
    {
        try {
            return DB::transaction(function () use ($actor, $employee, $data): Employee {
                Gate::forUser($this->people->currentAccount($actor))->authorize('manage-directory');
                $graph = Employee::query()->orderBy('id')->lockForUpdate()->get();
                $record = $employee === null ? new Employee : $graph->firstWhere('id', $employee->id);
                if (! $record instanceof Employee) {
                    abort(404);
                }
                $errors = [];
                if ($data['department_id'] !== null && Department::query()->whereKey($data['department_id'])->where('is_active', true)->lockForUpdate()->first(['id']) === null && ! (! $data['is_active'] && $record->exists && $record->department_id === $data['department_id'])) {
                    $errors['department_id'] = 'Choose an active department.';
                }
                if ($data['job_role_id'] !== null && JobRole::query()->whereKey($data['job_role_id'])->where('is_active', true)->lockForUpdate()->first(['id']) === null && ! (! $data['is_active'] && $record->exists && $record->job_role_id === $data['job_role_id'])) {
                    $errors['job_role_id'] = 'Choose an active job role.';
                }
                if ($data['manager_id'] !== null && ! $graph->contains(fn (Employee $candidate): bool => $candidate->id === $data['manager_id'] && $candidate->is_active) && ! (! $data['is_active'] && $record->exists && $record->manager_id === $data['manager_id'])) {
                    $errors['manager_id'] = 'Choose an active manager.';
                }
                if (! $data['is_active'] && $record->exists && $graph->contains(fn (Employee $report): bool => $report->manager_id === $record->id && $report->is_active)) {
                    $errors['is_active'] = 'Reassign active reports before retiring this person.';
                }
                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }
                $parents = [];
                foreach ($graph as $person) {
                    $parents[$person->id] = $person->manager_id;
                }
                $this->tree->assertManagerIsSafe($record->exists ? $record->id : null, $data['manager_id'], $parents);
                if (! $record->exists) {
                    $record->employee_number = $data['employee_number'];
                }
                $record->forceFill([
                    'name' => $data['name'], 'department_id' => $data['department_id'], 'job_role_id' => $data['job_role_id'],
                    'manager_id' => $data['manager_id'], 'is_active' => $data['is_active'],
                ])->save();
                $account = $record->user()->lockForUpdate()->first();
                if ($account !== null) {
                    $account->name = $record->name;
                    if (! $record->is_active && $account->is_active) {
                        $account->is_active = false;
                        $account->session_version++;
                    }
                    $account->save();
                }

                AuditEvent::query()->create(['actor_id' => $actor->id, 'event' => 'directory.employee.saved', 'subject_type' => 'employee', 'subject_id' => $record->id, 'details' => ['employee_number' => $record->employee_number, 'is_active' => $record->is_active]]);

                return $record;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['employee_number' => 'This employee ID is already in use.']);
        }
    }
}
