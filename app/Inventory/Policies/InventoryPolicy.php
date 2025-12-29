<?php

namespace App\Inventory\Policies;

use App\Inventory\Models\InventoryItem;
use App\Models\User;

class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'manager']) || $user->hasPermission('inventory.manage');
    }

    public function view(User $user, InventoryItem $item): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, InventoryItem $item): bool
    {
        return $this->viewAny($user);
    }
}

