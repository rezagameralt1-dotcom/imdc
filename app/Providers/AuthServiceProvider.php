<?php

namespace App\Providers;

use App\Dids\Models\DidProfile;
use App\Dids\Policies\DidPolicy;
use App\Inventory\Models\InventoryItem;
use App\Inventory\Policies\InventoryPolicy;
use App\Nfts\Models\NftToken;
use App\Nfts\Policies\NftPolicy;
use App\Orders\Models\Order;
use App\Orders\Policies\OrderPolicy;
use App\Products\Models\Product;
use App\Products\Policies\ProductPolicy;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Product::class => ProductPolicy::class,
        Order::class => OrderPolicy::class,
        InventoryItem::class => InventoryPolicy::class,
        NftToken::class => NftPolicy::class,
        DidProfile::class => DidPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function (User $user) {
            return $user->hasRole('Admin') ? true : null;
        });
    }
}



