<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

class StockMovementPolicy
{
    /**
     * Determine whether the user can view any stock movements.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the stock movement.
     */
    public function view(User $user, StockMovement $stockMovement): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create stock movements (e.g. Inbound).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Stock movements are immutable ledger records; updates are strictly prohibited.
     */
    public function update(User $user, StockMovement $stockMovement): bool
    {
        return false;
    }

    /**
     * Stock movements are immutable ledger records; deletion is strictly prohibited.
     */
    public function delete(User $user, StockMovement $stockMovement): bool
    {
        return false;
    }
}
