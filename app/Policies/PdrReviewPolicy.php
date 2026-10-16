<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PdrReview;
use App\Models\User;
use App\Services\PdrAccess;

class PdrReviewPolicy
{
    public function __construct(private PdrAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $user->hasNativeAccess();
    }

    public function view(User $user, PdrReview $review): bool
    {
        return $this->access->canView($user, $review);
    }

    public function editEmployee(User $user, PdrReview $review): bool
    {
        return $this->access->canEmployee($user, $review);
    }

    public function editManager(User $user, PdrReview $review): bool
    {
        return $this->access->canManager($user, $review);
    }
}
