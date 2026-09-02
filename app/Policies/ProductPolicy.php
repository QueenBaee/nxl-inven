<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether the user can view any products.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the product.
     */
    public function view(User $user, Product $product): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create products.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('owner');
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->hasRole('owner');
    }

    /**
     * Determine whether the user can delete the product.
     * Guarded: Products with existing stock movements, sales, or audit items cannot be deleted.
     */
    public function delete(User $user, Product $product): bool
    {
        if (! $user->hasRole('owner')) {
            return false;
        }

        // Prevent destructive deletion of products with existing inventory/sales audit history
        return ! $product->stockMovements()->exists();
    }
}
