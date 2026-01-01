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

    public function transfer(User $user): bool
    {
        // Allow admins or users with nft.transfer permission
        return $user->hasRole('Admin') || $user->hasPermission('nft.transfer');
    }

    public function read(User $user): bool
    {
        // Allow admins or users with nft.read permission
        return $user->hasRole('Admin') || $user->hasPermission('nft.read');
    }
}
