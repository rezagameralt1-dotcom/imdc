<?php

namespace App\Products\Policies;

use App\Models\User;
use App\Products\Models\Product;

class ProductPolicy
{
    private function isAuthenticated(User $user): bool
    {
        return (bool) $user->getAuthIdentifier();
    }

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
        if (! $this->isAuthenticated($user)) {
            return false;
        }

        return true;
    }

    public function update(User $user, Product $product): bool
    {
        if (! $this->isAuthenticated($user)) {
            return false;
        }

        return true;
    }
}



