<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PdrReview;
use App\Models\ReviewCycle;
use App\Models\User;
use App\PdrState;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PdrReview> */
class PdrReviewFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['review_cycle_id' => ReviewCycle::factory(), 'employee_id' => Employee::factory(), 'manager_user_id' => User::factory()->withEmployee(), 'section_profile' => 'production', 'section_template' => config('pdr.profiles.production'), 'state' => PdrState::Draft, 'version' => 1, 'allocated_by' => User::factory()->withEmployee()];
    }
}
