<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    /**
     * Determine whether the user can view any transactions.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the transaction.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        return true;
    }

    /**
     * Transactions are created via the POS Cart component.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Transactions are immutable; updates are prohibited.
     */
    public function update(User $user, Transaction $transaction): bool
    {
        return false;
    }

    /**
     * Historical sales transactions cannot be deleted.
     */
    public function delete(User $user, Transaction $transaction): bool
    {
        return false;
    }
}
