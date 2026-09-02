<?php

namespace App\Policies;

use App\Models\ConsignmentSettlement;
use App\Models\User;

class ConsignmentSettlementPolicy
{
    /**
     * Determine whether the user can view any settlements.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the settlement.
     */
    public function view(User $user, ConsignmentSettlement $settlement): bool
    {
        return true;
    }

    /**
     * Settlements are generated automatically via TransactionItemObserver.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Settlements cannot be edited directly via admin forms.
     */
    public function update(User $user, ConsignmentSettlement $settlement): bool
    {
        return false;
    }

    /**
     * Settlement deletion is strictly prohibited to preserve financial audit trail.
     */
    public function delete(User $user, ConsignmentSettlement $settlement): bool
    {
        return false;
    }

    /**
     * Payout execution is restricted to owners.
     */
    public function payout(User $user): bool
    {
        return $user->hasRole('owner');
    }
}
