<?php

declare(strict_types=1);

namespace App\Policies;

use App\Capability;
use App\Models\Skill;
use App\Models\User;

class SkillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCapability(Capability::ManageFrameworks);
    }

    public function view(User $user, Skill $skill): bool
    {
        return $user->hasCapability(Capability::ManageFrameworks);
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(Capability::ManageFrameworks);
    }

    public function update(User $user, Skill $skill): bool
    {
        return $user->hasCapability(Capability::ManageFrameworks);
    }
}
