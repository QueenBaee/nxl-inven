<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockOpnameApproved implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  list<int>  $itemIds
     */
    public function __construct(
        public readonly int $stockOpnameId,
        public readonly int $approvedBy,
        public readonly float $totalShopLoss,
        public readonly float $totalSupplierLiability,
        public readonly array $itemIds,
    ) {}
}
