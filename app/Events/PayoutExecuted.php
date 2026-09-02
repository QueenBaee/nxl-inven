<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayoutExecuted implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  list<int>  $settlementIds
     */
    public function __construct(
        public readonly int $supplierId,
        public readonly string $payoutReference,
        public readonly float $totalAmount,
        public readonly array $settlementIds,
    ) {}
}
