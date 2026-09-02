<?php

namespace App\Observers;

use App\Enums\OpnameStatus;
use App\Enums\StockMovementType;
use App\Exceptions\StockOpnameInProgressException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Support\Facades\DB;

class StockMovementObserver
{
    /**
     * Handle the StockMovement "creating" event.
     *
     * Enforces the "Freeze" guard: rejects any 'in' or 'out' movement if the product
     * is part of an active 'in_progress' Stock Opname session.
     *
     * @throws StockOpnameInProgressException
     */
    public function creating(StockMovement $stockMovement): void
    {
        // Adjustments from the opname reconciliation process are permitted
        if ($stockMovement->type === StockMovementType::Adjustment) {
            return;
        }

        $activeOpname = StockOpname::where('status', OpnameStatus::InProgress)
            ->whereHas('items', fn ($query) => $query->where('product_id', $stockMovement->product_id))
            ->first();

        if ($activeOpname) {
            throw new StockOpnameInProgressException(
                productId: $stockMovement->product_id,
                sessionName: $activeOpname->session_name,
            );
        }
    }

    /**
     * Handle the StockMovement "created" event.
     *
     * Atomically mutates the product's stock within a database transaction:
     * - 'in': increments stock atomically
     * - 'out': decrements stock atomically
     * - 'adjustment': directly syncs product stock to the verified physical_qty snapshot
     */
    public function created(StockMovement $stockMovement): void
    {
        DB::transaction(function () use ($stockMovement): void {
            if ($stockMovement->type === StockMovementType::In) {
                Product::where('id', $stockMovement->product_id)
                    ->increment('stock', $stockMovement->quantity);
            } elseif ($stockMovement->type === StockMovementType::Out) {
                Product::where('id', $stockMovement->product_id)
                    ->decrement('stock', $stockMovement->quantity);
            } elseif ($stockMovement->type === StockMovementType::Adjustment) {
                // Deterministically resolve physical_qty via the direct stockOpnameItem relation
                $physicalQty = $stockMovement->stockOpnameItem?->physical_qty;

                // Robust fallback if created outside direct relation link
                if ($physicalQty === null) {
                    $physicalQty = StockOpnameItem::where('product_id', $stockMovement->product_id)
                        ->whereHas('stockOpname', fn ($query) => $query->whereIn('status', [OpnameStatus::InProgress, OpnameStatus::Completed]))
                        ->latest('id')
                        ->value('physical_qty');
                }

                if ($physicalQty !== null) {
                    Product::where('id', $stockMovement->product_id)
                        ->lockForUpdate()
                        ->update(['stock' => $physicalQty]);
                }
            }
        });
    }
}
