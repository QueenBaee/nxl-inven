<?php

namespace App\Actions\StockOpname;

use App\Enums\OpnameStatus;
use App\Enums\StockMovementType;
use App\Events\StockOpnameApproved;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveStockOpnameAction
{
    /**
     * Approve a Stock Opname session, reconcile stock levels, and dispatch completion event.
     *
     * @throws ValidationException
     */
    public function execute(StockOpname $session, int $approverId): StockOpname
    {
        return DB::transaction(function () use ($session, $approverId): StockOpname {
            /** @var StockOpname $lockedSession */
            $lockedSession = StockOpname::where('id', $session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSession->status !== OpnameStatus::InProgress) {
                throw ValidationException::withMessages([
                    'session' => "Cannot approve session: Current status is '{$lockedSession->status->value}', but 'in_progress' is required.",
                ]);
            }

            if ($lockedSession->counting_completed_at === null) {
                throw ValidationException::withMessages([
                    'counting' => 'Penghitungan fisik belum diselesaikan. Selesaikan proses penghitungan sebelum melakukan approval.',
                ]);
            }

            // Lock all items associated with this session
            $items = $lockedSession->items()
                ->with('product')
                ->lockForUpdate()
                ->get();

            // Validate that no items remain uncounted
            $uncountedItems = $items->filter(fn (StockOpnameItem $item): bool => $item->physical_qty === null);

            if ($uncountedItems->isNotEmpty()) {
                $uncountedNames = $uncountedItems
                    ->take(5)
                    ->map(fn (StockOpnameItem $i) => "{$i->product->name} ({$i->product->sku})")
                    ->implode(', ');

                $remainingCount = $uncountedItems->count() - 5;
                if ($remainingCount > 0) {
                    $uncountedNames .= " and {$remainingCount} more";
                }

                throw ValidationException::withMessages([
                    'items' => "Approval blocked: {$uncountedItems->count()} product(s) have not been counted: {$uncountedNames}.",
                ]);
            }

            $totalShopLoss = 0.0;
            $totalSupplierLiability = 0.0;
            $itemIds = [];

            // Process inventory reconciliation for each audited product
            foreach ($items as $item) {
                $itemIds[] = $item->id;
                $variance = $item->variance;

                // Create adjustment StockMovement for any variance != 0
                if ($variance !== null && $variance !== 0) {
                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'quantity' => abs($variance),
                        'type' => StockMovementType::Adjustment,
                        'reference_note' => "Stock Opname Adjustment - {$lockedSession->session_name}",
                        'stock_opname_item_id' => $item->id,
                        'created_by' => $approverId,
                    ]);
                }

                // Categorize financial shortages based on historical snapshot
                if ($variance !== null && $variance < 0) {
                    if ($item->is_consignment_snapshot) {
                        $totalSupplierLiability += (float) $item->loss_value;
                    } else {
                        $totalShopLoss += (float) $item->loss_value;
                    }
                }
            }

            $lockedSession->update([
                'status' => OpnameStatus::Completed,
                'approved_by' => $approverId,
            ]);

            // Dispatch event for downstream accounting/reporting listeners (dispatched after commit)
            StockOpnameApproved::dispatch(
                $lockedSession->id,
                $approverId,
                $totalShopLoss,
                $totalSupplierLiability,
                $itemIds,
            );

            return $lockedSession;
        }, 5);
    }
}
