<?php

namespace App\Policies;

use App\Enums\OpnameStatus;
use App\Models\StockOpnameItem;
use App\Models\User;

class StockOpnameItemPolicy
{
    /**
     * Determine whether the user can view any items.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the specific item.
     */
    public function view(User $user, StockOpnameItem $item): bool
    {
        return true;
    }

    /**
     * Determine whether the user can enter or update physical counts.
     * Allowed only when the session is in_progress and counting has not been submitted.
     */
    public function update(User $user, StockOpnameItem $item): bool
    {
        return $item->stockOpname?->status === OpnameStatus::InProgress
            && $item->stockOpname?->counting_completed_at === null;
    }

    /**
     * Determine whether the user can view sensitive audit details (system_qty, variance, loss values).
     * Only owners have permission; staff perform blind counts.
     */
    public function viewAuditDetails(User $user): bool
    {
        return $user->hasRole('owner');
    }
}
