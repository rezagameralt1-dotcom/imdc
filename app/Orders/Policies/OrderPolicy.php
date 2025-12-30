<?php

namespace App\Orders\Policies;

use App\Models\User;
use App\Orders\Models\Order;

class OrderPolicy
{
    private function isAuthenticated(User $user): bool
    {
        return (bool) $user->getAuthIdentifier();
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->isAuthenticated($user);
    }

    public function update(User $user, Order $order): bool
    {
        return $this->isAuthenticated($user);
    }
}
