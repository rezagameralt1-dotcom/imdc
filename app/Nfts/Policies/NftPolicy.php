<?php

namespace App\Nfts\Policies;

use App\Models\User;
use App\Nfts\Models\NftToken;

class NftPolicy
{
    public function mint(User $user): bool
    {
        // Allow admins or users with nft.mint permission
        return $user->hasRole('Admin') || $user->hasPermission('nft.mint');
    }

    public function transfer(User $user, NftToken $token): bool
    {
        // Admin can transfer any token
        if ($user->hasRole('Admin')) {
            return true;
        }
        
        // Only owner can transfer their own token
        if ($token->owner_user_id == $user->id) {
            return true;
        }
        
        return false;
    }

    public function read(User $user): bool
    {
        // Allow admins or users with nft.read permission
        return $user->hasRole('Admin') || $user->hasPermission('nft.read');
    }
}
