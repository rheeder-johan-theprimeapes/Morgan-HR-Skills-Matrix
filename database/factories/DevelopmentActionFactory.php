<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DevelopmentAction;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DevelopmentAction> */
class DevelopmentActionFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['employee_id' => Employee::factory(), 'revision' => 0, 'origin_kind' => 'manual', 'origin_snapshot' => ['kind' => 'manual', 'label' => 'Illustrative development plan'], 'created_by' => User::factory()->withEmployee()];
    }
}
