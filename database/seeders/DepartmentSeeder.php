<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;
use LogicException;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Fictional catalogue fixtures are restricted to local/testing.');
        }
        Department::query()->firstOrCreate(['code' => 'DEMO-DEP'], ['name' => 'Fictional Department']);
    }
}
