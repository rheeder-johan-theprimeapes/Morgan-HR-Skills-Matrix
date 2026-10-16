<?php

declare(strict_types=1);

namespace App\Services;

use App\AssessmentKind;
use App\Capability;
use App\Models\Assessment;
use App\Models\Employee;
use App\Models\User;

class AssessmentAccess
{
    public function __construct(private EmployeeAccess $people, private ReportingTree $tree) {}

    public function canSupervise(User $user, Employee $employee): bool
    {
        $account = $this->people->currentAccount($user);
        if ($account === null || ! $account->hasNativeAccess() || $account->employee === null || $account->employee_id === $employee->id) {
            return false;
        }
        if ($account->hasCapability(Capability::AdministerReviews) && $this->people->canView($account, $employee)) {
            return true;
        }

        return in_array($employee->id, $this->tree->descendantIds($account->employee), true);
    }

    /** @return list<int> */
    public function supervisedIds(User $user): array
    {
        $account = $this->people->currentAccount($user);
        if ($account === null || ! $account->hasNativeAccess() || $account->employee === null) {
            return [];
        }
        if ($account->hasCapability(Capability::AdministerReviews)) {
            return array_values($this->people->query($account)->where('id', '!=', $account->employee_id)->get(['id'])->map(fn (Employee $employee): int => $employee->id)->all());
        }

        return $this->tree->descendantIds($account->employee);
    }

    public function canRecord(User $user, Employee $employee, AssessmentKind $kind): bool
    {
        $account = $this->people->currentAccount($user);
        if ($account === null || ! $account->hasNativeAccess() || ! $employee->is_active) {
            return false;
        }

        return $kind === AssessmentKind::Self ? $account->employee_id === $employee->id : $this->canSupervise($account, $employee);
    }

    public function canView(User $user, Assessment $assessment): bool
    {
        $account = $this->people->currentAccount($user);
        $employee = $assessment->employee;
        if ($account === null || ! $account->hasNativeAccess() || $employee === null) {
            return false;
        }

        return ($assessment->kind === AssessmentKind::Self && $account->employee_id === $employee->id) || $this->canSupervise($account, $employee);
    }
}
