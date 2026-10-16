<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AccountAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccountAudit> */
class AccountAuditFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['actor_id' => null, 'subject_id' => User::factory()->withEmployee(), 'action' => 'account.provisioned'];
    }
}
