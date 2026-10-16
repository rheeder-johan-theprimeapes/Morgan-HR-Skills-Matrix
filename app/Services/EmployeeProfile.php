<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;

class EmployeeProfile
{
    /** @return array{id: int, employee_number: string, name: string, department: string|null, job_role: string|null, manager: string|null, is_active: bool} */
    public static function fromEmployee(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'name' => $employee->name,
            'department' => $employee->department?->name,
            'job_role' => $employee->jobRole?->name,
            'manager' => $employee->manager?->name,
            'is_active' => $employee->is_active,
        ];
    }
}
