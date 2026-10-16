<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditEvent> */
class AuditEventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['actor_id' => User::factory()->withEmployee(), 'event' => 'fixture.example', 'subject_type' => 'employee', 'subject_id' => Employee::factory(), 'details' => []];
    }
}
