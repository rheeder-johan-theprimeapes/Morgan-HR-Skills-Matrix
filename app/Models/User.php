<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Capability;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'session_version', 'can_administer_accounts', 'can_manage_directory', 'capabilities'])]
/** @property list<string>|null $capabilities */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function canManageDirectory(): bool
    {
        return $this->hasCapability(Capability::ManageDirectory);
    }

    public function hasCapability(Capability $capability): bool
    {
        if (! $this->is_active || $this->employee?->is_active !== true) {
            return false;
        }

        return match ($capability) {
            Capability::AdministerAccounts => $this->can_administer_accounts,
            Capability::ManageDirectory => $this->can_manage_directory,
            default => in_array($capability->value, $this->capabilities ?? [], true),
        };
    }

    public function hasNativeAccess(): bool
    {
        return $this->is_active && $this->employee?->is_active === true && $this->password !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array{email_verified_at: 'datetime', password: 'hashed', is_active: 'boolean', can_administer_accounts: 'boolean', can_manage_directory: 'boolean', session_version: 'integer', capabilities: 'array'}
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'can_administer_accounts' => 'boolean',
            'can_manage_directory' => 'boolean',
            'session_version' => 'integer',
            'capabilities' => 'array',
        ];
    }
}
