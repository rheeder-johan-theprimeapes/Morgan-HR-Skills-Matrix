<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JobRole;
use App\Models\PayBenchmark;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayBenchmark> */
class PayBenchmarkFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['job_role_id' => JobRole::factory(), 'title' => 'Illustrative benchmark', 'company_context' => 'Fictional company', 'duties' => 'Illustrative duties', 'limitations' => 'Synthetic; not market advice', 'source' => 'Fictional survey', 'source_date' => '2026-01-01', 'valid_from' => '2026-01-01', 'valid_to' => '2026-12-31', 'amount_minor' => 2500, 'currency' => 'GBP', 'basis' => 'hourly', 'weekly_hours_hundredths' => 3750, 'weeks_per_year_hundredths' => 5200, 'recorded_by' => User::factory()->withEmployee()];
    }
}
