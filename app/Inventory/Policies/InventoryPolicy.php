<?php

namespace App\Inventory\Policies;

use App\Inventory\Models\InventoryItem;
use App\Models\User;

class InventoryPolicy
{
    /**
     * Read access for marketplace users:
     * Any authenticated user can view inventory (read-only).
     * Write access remains restricted to privileged roles/permissions.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InventoryItem $item): bool
    {
        return true;
    }

    public function update(User $user, InventoryItem $item): bool
    {
        return $user->hasRole(['admin', 'manager']) || $user->hasPermission('inventory.manage');
    }
}
