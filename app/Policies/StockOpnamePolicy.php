<?php

namespace App\Policies;

use App\Enums\OpnameStatus;
use App\Models\StockOpname;
use App\Models\User;

class StockOpnamePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * Owner can view all sessions. Staff can only view active in_progress sessions for blind counting.
     */
    public function view(User $user, StockOpname $stockOpname): bool
    {
        if ($user->hasRole('owner')) {
            return true;
        }

        // Staff can only view active in-progress sessions for physical counting
        return $stockOpname->status === OpnameStatus::InProgress;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('owner');
    }

    /**
     * Determine whether the user can update the model metadata (session name).
     */
    public function update(User $user, StockOpname $stockOpname): bool
    {
        return $user->hasRole('owner') && $stockOpname->status === OpnameStatus::Draft;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StockOpname $stockOpname): bool
    {
        return $user->hasRole('owner') && $stockOpname->status === OpnameStatus::Draft;
    }

    /**
     * Determine whether the user can start an opname session.
     */
    public function start(User $user, StockOpname $stockOpname): bool
    {
        return $user->hasRole('owner') && $stockOpname->status === OpnameStatus::Draft;
    }

    /**
     * Determine whether the user can submit the completed physical count.
     */
    public function submitCount(User $user, StockOpname $stockOpname): bool
    {
        return $stockOpname->status === OpnameStatus::InProgress
            && $stockOpname->counting_completed_at === null;
    }

    /**
     * Determine whether the user can reopen physical counting for corrections (owner only).
     */
    public function reopenCount(User $user, StockOpname $stockOpname): bool
    {
        return $user->hasRole('owner')
            && $stockOpname->status === OpnameStatus::InProgress
            && $stockOpname->counting_completed_at !== null;
    }

    /**
     * Determine whether the user can approve and reconcile an opname session.
     */
    public function approve(User $user, StockOpname $stockOpname): bool
    {
        return $user->hasRole('owner') && $stockOpname->status === OpnameStatus::InProgress;
    }
}
