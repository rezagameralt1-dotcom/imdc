<?php

namespace App\Products\Policies;

use App\Models\User;
use App\Products\Models\Product;

class ProductPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Product $product): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, Product $product): bool { return true; }
    public function delete(User $user, Product $product): bool { return true; }
}
