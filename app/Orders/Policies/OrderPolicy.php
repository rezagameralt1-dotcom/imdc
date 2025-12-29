<?php

namespace App\Orders\Policies;

use App\Models\User;
use App\Orders\Models\Order;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'manager']) || $user->hasPermission('orders.manage');
    }

    public function view(User $user, Order $order): bool
    {
        return $this->viewAny($user) || $order->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'manager', 'customer']) || $user->hasPermission('orders.manage');
    }

    public function update(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }
}

