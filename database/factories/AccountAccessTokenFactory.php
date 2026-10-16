<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AccountAccessToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AccountAccessToken> */
class AccountAccessTokenFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory()->withEmployee()->pendingSetup(), 'token_hash' => hash('sha256', Str::random(64)), 'expires_at' => now()->addMinutes(30), 'used_at' => null];
    }
}
