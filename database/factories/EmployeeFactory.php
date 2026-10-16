<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['employee_number' => fake()->unique()->numerify('EMP-########'), 'name' => fake()->name(), 'is_active' => true];
    }
}
