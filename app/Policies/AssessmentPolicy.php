<?php

declare(strict_types=1);

namespace App\Policies;

use App\AssessmentKind;
use App\Models\Assessment;
use App\Models\Employee;
use App\Models\User;
use App\Services\AssessmentAccess;

class AssessmentPolicy
{
    public function __construct(private AssessmentAccess $access) {}

    public function create(User $user, Employee $employee, AssessmentKind $kind): bool
    {
        return $this->access->canRecord($user, $employee, $kind);
    }

    public function view(User $user, Assessment $assessment): bool
    {
        return $this->access->canView($user, $assessment);
    }
}
