<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;
use LogicException;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Fictional employee fixtures are restricted to local/testing environments.');
        }
        foreach (['DEMO-001' => 'Fictional Production Employee', 'DEMO-002' => 'Fictional Office Employee'] as $number => $name) {
            Employee::query()->firstOrCreate(['employee_number' => $number], ['name' => $name]);
        }
    }
}
