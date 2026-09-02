<?php

namespace App\Actions\StockOpname;

use App\Enums\OpnameStatus;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitStockOpnameCountAction
{
    /**
     * Submit physical count for owner review.
     *
     * @throws ValidationException
     */
    public function execute(StockOpname $session, int $userId): StockOpname
    {
        return DB::transaction(function () use ($session, $userId): StockOpname {
            /** @var StockOpname $lockedSession */
            $lockedSession = StockOpname::where('id', $session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSession->status !== OpnameStatus::InProgress) {
                throw ValidationException::withMessages([
                    'session' => "Sesi Stock Opname tidak dalam status 'in_progress'. Status saat ini: '{$lockedSession->status->value}'.",
                ]);
            }

            if ($lockedSession->counting_completed_at !== null) {
                throw ValidationException::withMessages([
                    'session' => 'Penghitungan fisik untuk sesi ini sudah dikirimkan sebelumnya.',
                ]);
            }

            // Lock all items associated with this session
            $items = $lockedSession->items()
                ->with('product')
                ->lockForUpdate()
                ->get();

            // Validate that no items remain uncounted (physical_qty === null)
            $uncountedItems = $items->filter(fn (StockOpnameItem $item): bool => $item->physical_qty === null);

            if ($uncountedItems->isNotEmpty()) {
                $uncountedNames = $uncountedItems
                    ->take(5)
                    ->map(fn (StockOpnameItem $i) => "{$i->product->name} ({$i->product->sku})")
                    ->implode(', ');

                $remainingCount = $uncountedItems->count() - 5;
                if ($remainingCount > 0) {
                    $uncountedNames .= " dan {$remainingCount} produk lainnya";
                }

                throw ValidationException::withMessages([
                    'items' => "Masih ada {$uncountedItems->count()} produk yang belum dihitung: {$uncountedNames}.",
                ]);
            }

            $lockedSession->update([
                'counting_completed_at' => now(),
                'counting_completed_by' => $userId,
            ]);

            return $lockedSession;
        }, 5);
    }
}

