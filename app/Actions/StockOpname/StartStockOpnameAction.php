<?php

namespace App\Actions\StockOpname;

use App\Enums\OpnameStatus;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\StockOpname;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartStockOpnameAction
{
    /**
     * Start a Stock Opname session: snapshot active products and freeze stock mutations.
     *
     * @throws ValidationException
     */
    public function execute(StockOpname $session): StockOpname
    {
        return DB::transaction(function () use ($session): StockOpname {
            /** @var StockOpname $lockedSession */
            $lockedSession = StockOpname::where('id', $session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSession->status !== OpnameStatus::Draft) {
                throw ValidationException::withMessages([
                    'session' => "Cannot start session: Current status is '{$lockedSession->status->value}', but 'draft' is required.",
                ]);
            }

            // Concurrency guard: Only ONE session globally may be in_progress
            $activeSessionExists = StockOpname::where('status', OpnameStatus::InProgress)
                ->where('id', '!=', $lockedSession->id)
                ->lockForUpdate()
                ->exists();

            if ($activeSessionExists) {
                throw ValidationException::withMessages([
                    'session' => 'Another Stock Opname session is currently in progress. Only one active audit session is permitted at a time.',
                ]);
            }

            // Snapshot all active products under lock to guarantee point-in-time consistency
            $products = Product::lockForUpdate()->get();

            foreach ($products as $product) {
                $lockedSession->items()->create([
                    'product_id' => $product->id,
                    'system_qty' => (int) $product->stock,
                    'cost_price_snapshot' => (float) $product->cost_price,
                    'is_consignment_snapshot' => $product->type === ProductType::Consignment,
                    'physical_qty' => null,
                ]);
            }

            $lockedSession->update([
                'status' => OpnameStatus::InProgress,
            ]);

            return $lockedSession;
        }, 5);
    }
}
