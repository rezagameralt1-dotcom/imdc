<?php

namespace App\Products\Policies;

use App\Models\User;
use App\Products\Models\Product;

class ProductPolicy
{
    public function viewAny(User $user): bool 
    { 
        return true; 
    }
    
    public function view(User $user, Product $product): bool 
    { 
        return true; 
    }
    
    public function create(User $user): bool 
    { 
        // Allow if user has products.create permission or is Seller/Admin
        return $user->can('products.create') 
            || $user->hasRole(['Seller', 'Admin']);
    }
    
    public function update(User $user, Product $product): bool 
    { 
        // Owner or Admin can update
        if ($user->hasRole('Admin')) {
            return true;
        }
        
        // Seller can update their own products
        return $product->seller_id === $user->id 
            && ($user->hasRole('Seller') || $user->can('products.update'));
    }
    
    public function delete(User $user, Product $product): bool 
    { 
        // Owner or Admin can delete
        if ($user->hasRole('Admin')) {
            return true;
        }
        
        return $product->seller_id === $user->id 
            && ($user->hasRole('Seller') || $user->can('products.delete'));
    }
}
