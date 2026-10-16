<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayRecord> */
class PayRecordFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['employee_id' => Employee::factory(), 'sequence' => 1, 'effective_on' => '2026-01-01', 'amount_minor' => 3000000, 'currency' => 'GBP', 'basis' => 'annual', 'weekly_hours_hundredths' => null, 'weeks_per_year_hundredths' => null, 'source' => 'Fictional pay source — test only', 'recorded_by' => User::factory()->withEmployee()];
    }
}
