<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => 'SKU-'.fake()->unique()->numerify('#####'),
            'name' => fake()->words(3, true),
            'stock' => 0,
            'type' => ProductType::Regular,
            'supplier_id' => null,
            'cost_price' => fake()->randomFloat(2, 10, 100),
            'selling_price' => fake()->randomFloat(2, 120, 200),
        ];
    }

    public function consignment(?Supplier $supplier = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::Consignment,
            'supplier_id' => $supplier?->id ?? Supplier::factory(),
        ]);
    }
}
