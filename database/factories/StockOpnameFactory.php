<?php

namespace Database\Factories;

use App\Enums\OpnameStatus;
use App\Models\StockOpname;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockOpname>
 */
class StockOpnameFactory extends Factory
{
    protected $model = StockOpname::class;

    public function definition(): array
    {
        return [
            'session_name' => 'Opname - '.fake()->monthName().' '.now()->year,
            'status' => OpnameStatus::Draft,
            'created_by' => User::factory(),
            'approved_by' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OpnameStatus::InProgress,
        ]);
    }

    public function completed(?User $approver = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OpnameStatus::Completed,
            'approved_by' => $approver?->id ?? User::factory(),
        ]);
    }
}
