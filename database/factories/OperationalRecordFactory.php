<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\OperationalRecord;
use App\Models\User;
use App\OperationalMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OperationalRecord> */
class OperationalRecordFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['employee_id' => Employee::factory(), 'sequence' => 1, 'metric' => OperationalMetric::AbsenceHours, 'unit' => 'hours', 'value_hundredths' => 350, 'is_interpretation' => false, 'period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'source' => 'Fictional test register', 'notes' => 'Synthetic context', 'recorded_by' => User::factory()->withEmployee()];
    }
}
