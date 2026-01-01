<?php

namespace App\Dids\Policies;

use App\Models\User;
use App\Dids\Models\DidProfile;

class DidPolicy
{
    /**
     * Check if user can manage their own DID profile
     *
     * @param User $user
     * @param DidProfile $didProfile
     * @return bool
     */
    public function manage(User $user, DidProfile $didProfile): bool
    {
        // Admin can manage any DID profile
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Users can only manage their own DID profile
        return $user->id === $didProfile->user_id;
    }
}
