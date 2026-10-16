<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'login_id' => fake()->unique()->numerify('USR-########'),
            'is_active' => true,
            'can_administer_accounts' => false,
            'can_manage_directory' => false,
            'session_version' => 1,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function withEmployee(): static
    {
        return $this->for(Employee::factory());
    }

    public function accountAdministrator(): static
    {
        return $this->withEmployee()->state(fn (): array => ['can_administer_accounts' => true]);
    }

    public function directoryAdministrator(): static
    {
        return $this->withEmployee()->state(fn (): array => ['can_manage_directory' => true]);
    }

    public function pendingSetup(): static
    {
        return $this->state(fn (): array => ['password' => null]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
