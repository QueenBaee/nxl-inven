<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'invoice_number' => 'INV-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'total_amount' => fake()->randomFloat(2, 50, 500),
            'payment_method' => PaymentMethod::Cash,
            'channel' => SalesChannel::Offline,
            'status' => TransactionStatus::Completed,
            'created_by' => User::factory(),
        ];
    }
}
