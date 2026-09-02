<?php

namespace Database\Factories;

use App\Enums\SettlementStatus;
use App\Models\ConsignmentSettlement;
use App\Models\Supplier;
use App\Models\TransactionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsignmentSettlement>
 */
class ConsignmentSettlementFactory extends Factory
{
    protected $model = ConsignmentSettlement::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'transaction_item_id' => TransactionItem::factory(),
            'amount' => fake()->randomFloat(2, 50, 500),
            'status' => SettlementStatus::Unpaid,
            'payout_reference' => null,
            'paid_at' => null,
        ];
    }

    public function paid(string $payoutReference = 'PAYOUT-1-20260901-0001'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SettlementStatus::Paid,
            'payout_reference' => $payoutReference,
            'paid_at' => now(),
        ]);
    }
}
