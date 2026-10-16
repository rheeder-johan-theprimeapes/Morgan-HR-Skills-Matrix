<?php

declare(strict_types=1);

namespace App;

enum DocumentVisibility: string
{
    case HrOnly = 'hr_only';
    case Supervisors = 'supervisors';
    case EmployeeShared = 'employee_shared';

    public function label(): string
    {
        return match ($this) {
            self::HrOnly => 'HR only',self::Supervisors => 'HR and current supervisors',self::EmployeeShared => 'HR, current supervisors and employee'
        };
    }
}
