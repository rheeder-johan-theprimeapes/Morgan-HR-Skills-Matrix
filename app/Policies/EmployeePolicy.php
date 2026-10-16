<?php

declare(strict_types=1);

namespace App\Policies;

use App\Capability;
use App\Models\Employee;
use App\Models\User;
use App\Services\AssessmentAccess;
use App\Services\EmployeeAccess;
use App\Services\HistoricalDocumentAccess;

class EmployeePolicy
{
    public function __construct(private EmployeeAccess $access, private AssessmentAccess $assessments, private HistoricalDocumentAccess $documents) {}

    public function viewAny(User $user): bool
    {
        return $user->hasNativeAccess();
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->access->canView($user, $employee);
    }

    public function viewAssessments(User $user, Employee $employee): bool
    {
        return $this->access->canView($user, $employee) && ($user->employee_id === $employee->id || $this->assessments->canSupervise($user, $employee));
    }

    public function viewHistoricalDocuments(User $user, Employee $employee): bool
    {
        return $this->documents->canList($user, $employee);
    }

    public function viewOperationalContext(User $user, Employee $employee): bool
    {
        $account = $this->access->currentAccount($user);

        return $account !== null && $account->hasCapability(Capability::SensitiveContext) && $this->access->canView($account, $employee);
    }

    public function viewPay(User $user, Employee $employee): bool
    {
        $account = $this->access->currentAccount($user);

        return $account !== null && $account->hasCapability(Capability::ActualPay) && $this->access->canView($account, $employee);
    }

    public function viewScore(User $user, Employee $employee): bool
    {
        return $this->assessments->canSupervise($user, $employee);
    }

    public function create(User $user): bool
    {
        return $user->canManageDirectory();
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->canManageDirectory();
    }

    public function linkAccount(User $user, Employee $employee): bool
    {
        return $user->canManageDirectory() && $user->can_administer_accounts;
    }
}
