<?php

namespace App\Nfts\Policies;

use App\Models\User;
use App\Nfts\Models\NftToken;

class NftPolicy
{
    public function mint(User $user): bool
    {
        return $user->hasPermission('nft.mint');
    }

    public function transfer(User $user): bool
    {
        return $user->hasPermission('nft.transfer');
    }

    public function read(User $user): bool
    {
        return $user->hasPermission('nft.read');
    }
}
