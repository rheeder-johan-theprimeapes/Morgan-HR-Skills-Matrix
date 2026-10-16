<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->create($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->can_administer_accounts && $user->employee?->is_active === true;
    }

    public function view(User $user, User $subject): bool
    {
        return $this->create($user);
    }

    public function issueAccess(User $user, User $subject): bool
    {
        return $this->create($user);
    }
}
