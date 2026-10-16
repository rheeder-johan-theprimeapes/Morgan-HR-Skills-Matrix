<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AccountAudit;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProvisionAccount
{
    /** @param array{employee_number: string, name: string, email: ?string} $data */
    public function handle(User $actor, array $data): User
    {
        try {
            return DB::transaction(function () use ($actor, $data): User {
                $currentActor = User::query()->lockForUpdate()->findOrFail($actor->id);
                Gate::forUser($currentActor)->authorize('create', User::class);
                $employee = Employee::query()->create(['employee_number' => $data['employee_number'], 'name' => $data['name']]);

                return $this->createAccount($currentActor, $employee, $data['email']);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['employee_number' => 'This employee ID or email is already in use.']);
        }
    }

    public function forEmployee(User $actor, Employee $employee, ?string $email = null): User
    {
        try {
            return DB::transaction(function () use ($actor, $employee, $email): User {
                $currentActor = User::query()->lockForUpdate()->findOrFail($actor->id);
                $person = Employee::query()->lockForUpdate()->findOrFail($employee->id);
                Gate::forUser($currentActor)->authorize('linkAccount', $person);
                if (! $person->is_active || $person->user()->exists()) {
                    throw ValidationException::withMessages(['account' => 'This person already has an account or is retired.']);
                }

                return $this->createAccount($currentActor, $person, $email);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['account' => 'This person or email already has an account.']);
        }
    }

    private function createAccount(User $actor, Employee $employee, ?string $email): User
    {
        $account = new User;
        $account->forceFill([
            'employee_id' => $employee->id, 'login_id' => $employee->employee_number,
            'name' => $employee->name, 'email' => $email, 'password' => null,
            'is_active' => true, 'can_administer_accounts' => false, 'session_version' => 1,
        ])->save();
        AccountAudit::query()->create(['actor_id' => $actor->id, 'subject_id' => $account->id, 'action' => 'account.provisioned']);

        return $account;
    }
}
