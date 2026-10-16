<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AccountAudit;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LinkEmployeeAccount
{
    public function __construct(private EmployeeAccess $people) {}

    public function handle(User $actor, Employee $employee, User $account): void
    {
        try {
            DB::transaction(function () use ($actor, $employee, $account): void {
                $person = Employee::query()->lockForUpdate()->findOrFail($employee->id);
                $linked = User::query()->lockForUpdate()->findOrFail($account->id);
                Gate::forUser($this->people->currentAccount($actor))->authorize('linkAccount', $person);
                if (! $person->is_active || $linked->employee_id !== null || $person->user()->exists()) {
                    throw ValidationException::withMessages(['account_id' => 'Choose an unlinked account and an active person without an account.']);
                }
                $linked->forceFill([
                    'employee_id' => $person->id, 'login_id' => $person->employee_number, 'name' => $person->name,
                    'password' => null, 'remember_token' => Str::random(60), 'session_version' => $linked->session_version + 1,
                ])->save();
                AccountAudit::query()->create(['actor_id' => $actor->id, 'subject_id' => $linked->id, 'action' => 'account.linked']);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['account_id' => 'The selected person or login ID already has an account.']);
        }
    }
}
