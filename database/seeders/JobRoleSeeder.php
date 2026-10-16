<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\JobRole;
use Illuminate\Database\Seeder;
use LogicException;

class JobRoleSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Fictional catalogue fixtures are restricted to local/testing.');
        }
        JobRole::query()->firstOrCreate(['code' => 'DEMO-ROLE'], ['name' => 'Fictional JobRole']);
    }
}
