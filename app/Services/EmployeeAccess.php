<?php

declare(strict_types=1);

namespace App\Services;

use App\Capability;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EmployeeAccess
{
    public function __construct(private ReportingTree $tree) {}

    /** @return Builder<Employee> */
    public function query(User $user): Builder
    {
        $account = $this->currentAccount($user);
        $query = Employee::query();
        if ($account === null || ! $account->hasNativeAccess() || $account->employee === null) {
            return $query->whereRaw('1 = 0');
        }
        if ($account->hasCapability(Capability::ManageDirectory) || $account->hasCapability(Capability::ViewOrganisation)) {
            return $query;
        }

        return $query->whereIn('id', [$account->employee_id, ...$this->tree->descendantIds($account->employee)]);
    }

    public function currentAccount(User $user): ?User
    {
        if (DB::transactionLevel() === 0) {
            return User::query()->with('employee')->find($user->id);
        }
        $account = User::query()->lockForUpdate()->find($user->id);
        if ($account !== null) {
            $account->setRelation('employee', $account->employee_id === null ? null : Employee::query()->lockForUpdate()->find($account->employee_id));
        }

        return $account;
    }

    public function canView(User $user, Employee $employee): bool
    {
        return $this->query($user)->whereKey($employee->id)->exists();
    }
}
