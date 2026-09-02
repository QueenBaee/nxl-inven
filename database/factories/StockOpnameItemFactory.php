<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockOpnameItem>
 */
class StockOpnameItemFactory extends Factory
{
    protected $model = StockOpnameItem::class;

    public function definition(): array
    {
        $product = Product::factory()->create();

        return [
            'stock_opname_id' => StockOpname::factory(),
            'product_id' => $product->id,
            'system_qty' => $product->stock,
            'cost_price_snapshot' => $product->cost_price,
            'physical_qty' => null,
            'is_consignment_snapshot' => false,
        ];
    }
}
