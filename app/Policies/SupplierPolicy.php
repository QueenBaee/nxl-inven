<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    /**
     * Determine whether the user can view any suppliers.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the supplier.
     */
    public function view(User $user, Supplier $supplier): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create suppliers.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('owner');
    }

    /**
     * Determine whether the user can update the supplier.
     */
    public function update(User $user, Supplier $supplier): bool
    {
        return $user->hasRole('owner');
    }

    /**
     * Determine whether the user can delete the supplier.
     * Guarded: Suppliers with associated products or settlements cannot be deleted.
     */
    public function delete(User $user, Supplier $supplier): bool
    {
        if (! $user->hasRole('owner')) {
            return false;
        }

        return ! $supplier->products()->exists() && ! $supplier->consignmentSettlements()->exists();
    }
}
