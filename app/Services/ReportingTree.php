<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportingTree
{
    /** @param array<int, int|null> $parents */
    public function assertManagerIsSafe(?int $employeeId, ?int $managerId, array $parents): void
    {
        $visited = [];
        while ($managerId !== null) {
            if ($managerId === $employeeId || isset($visited[$managerId])) {
                throw ValidationException::withMessages(['manager_id' => 'Choose a manager outside this reporting chain.']);
            }
            $visited[$managerId] = true;
            $managerId = $parents[$managerId] ?? null;
        }
    }

    /** @return list<int> */
    public function descendantIds(Employee $manager): array
    {
        $children = [];
        $query = Employee::query()->orderBy('id');
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }
        foreach ($query->get(['id', 'manager_id']) as $employee) {
            if ($employee->manager_id !== null) {
                $children[$employee->manager_id][] = $employee->id;
            }
        }
        $pending = $children[$manager->id] ?? [];
        $descendants = [];
        $visited = [$manager->id => true];
        while ($pending !== []) {
            $id = array_shift($pending);
            if (isset($visited[$id])) {
                return [];
            }
            $visited[$id] = true;
            $descendants[] = $id;
            array_push($pending, ...($children[$id] ?? []));
        }
        sort($descendants);

        return $descendants;
    }
}
