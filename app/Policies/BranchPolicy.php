<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function view(User $user, Branch $branch): bool
    {
        if (! $user->is_active || $branch->trashed()) {
            return false;
        }
        $branch->loadMissing('business');
        if (! $branch->business) {
            return false;
        }

        return $user->hasRole('PLATFORM_ADMIN')
            || $user->hasRole('BUSINESS_OWNER', $branch->business_id)
            || $user->hasRole('BRANCH_MANAGER', $branch->business_id, $branch->id)
            || $user->hasRole('RECEPTIONIST', $branch->business_id, $branch->id)
            || $user->hasRole('STAFF', $branch->business_id, $branch->id);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->view($user, $branch)
            && ($user->hasRole('PLATFORM_ADMIN')
                || $user->hasRole('BUSINESS_OWNER', $branch->business_id)
                || $user->hasRole('BRANCH_MANAGER', $branch->business_id, $branch->id));
    }

    public function manageBookings(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch) || ($this->view($user, $branch)
            && $user->hasRole('RECEPTIONIST', $branch->business_id, $branch->id));
    }
}
