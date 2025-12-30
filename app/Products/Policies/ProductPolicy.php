<?php

namespace App\Products\Policies;

use App\Models\User;
use App\Products\Models\Product;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'manager']) || $user->hasPermission('products.manage');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'manager']) || $user->hasPermission('products.manage');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->create($user);
    }
}



