<?php

namespace App\Observers;

use App\Enums\SettlementStatus;
use App\Models\ConsignmentSettlement;
use App\Models\TransactionItem;

class TransactionItemObserver
{
    /**
     * Handle the TransactionItem "created" event.
     *
     * If the item is consignment, creates a ConsignmentSettlement record idempotently
     * using the historical cost_price and quantity snapshot, and snapshots the supplier_id.
     */
    public function created(TransactionItem $transactionItem): void
    {
        if ($transactionItem->is_consignment) {
            $supplierId = $transactionItem->product?->supplier_id
                ?? $transactionItem->product()->value('supplier_id');

            if ($supplierId) {
                $amount = bcmul((string) $transactionItem->cost_price, (string) $transactionItem->quantity, 2);

                ConsignmentSettlement::firstOrCreate(
                    [
                        'transaction_item_id' => $transactionItem->id,
                    ],
                    [
                        'supplier_id' => $supplierId,
                        'amount' => $amount,
                        'status' => SettlementStatus::Unpaid,
                    ]
                );
            }
        }
    }
}
