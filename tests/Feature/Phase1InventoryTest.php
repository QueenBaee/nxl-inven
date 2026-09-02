<?php

use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can create supplier with required and optional fields', function () {
    $supplier = Supplier::create([
        'name' => 'PT Sumber Makmur',
        'contact_person' => 'Budi Santoso',
        'phone' => '08123456789',
        'address' => 'Jl. Jenderal Sudirman No. 12',
    ]);

    expect($supplier)->toBeInstanceOf(Supplier::class)
        ->and($supplier->name)->toBe('PT Sumber Makmur')
        ->and($supplier->contact_person)->toBe('Budi Santoso')
        ->and($supplier->phone)->toBe('08123456789')
        ->and($supplier->address)->toBe('Jl. Jenderal Sudirman No. 12');
});

test('regular product does not require supplier_id', function () {
    $product = Product::create([
        'sku' => 'REG-001',
        'name' => 'Regular Chalk',
        'stock' => 0,
        'type' => ProductType::Regular,
        'supplier_id' => null,
        'cost_price' => 15000.00,
        'selling_price' => 25000.00,
    ]);

    expect($product->exists)->toBeTrue()
        ->and($product->type)->toBe(ProductType::Regular)
        ->and($product->supplier_id)->toBeNull()
        ->and($product->cost_price)->toBe('15000.00')
        ->and($product->selling_price)->toBe('25000.00');
});

test('consignment product requires supplier_id at model level', function () {
    expect(function () {
        Product::create([
            'sku' => 'CON-001',
            'name' => 'Consignment Cue Tip',
            'stock' => 0,
            'type' => ProductType::Consignment,
            'supplier_id' => null,
            'cost_price' => 50000.00,
            'selling_price' => 75000.00,
        ]);
    })->toThrow(InvalidArgumentException::class, 'Consignment products must have an assigned supplier');
});

test('consignment product with supplier_id succeeds', function () {
    $supplier = Supplier::factory()->create();

    $product = Product::create([
        'sku' => 'CON-002',
        'name' => 'Consignment Cue Tip Valid',
        'stock' => 0,
        'type' => ProductType::Consignment,
        'supplier_id' => $supplier->id,
        'cost_price' => 50000.00,
        'selling_price' => 75000.00,
    ]);

    expect($product->exists)->toBeTrue()
        ->and($product->supplier->id)->toBe($supplier->id)
        ->and($supplier->products->first()->id)->toBe($product->id);
});

test('stock movement of type in atomically increments product stock via observer', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 10]);

    expect($product->fresh()->stock)->toBe(10);

    $movement1 = StockMovement::create([
        'product_id' => $product->id,
        'quantity' => 25,
        'type' => StockMovementType::In,
        'reference_note' => 'Restock shipment batch 1',
        'created_by' => $user->id,
    ]);

    expect($product->fresh()->stock)->toBe(35);

    $movement2 = StockMovement::create([
        'product_id' => $product->id,
        'quantity' => 15,
        'type' => StockMovementType::In,
        'reference_note' => 'Titipan Ko Hendra',
        'created_by' => $user->id,
    ]);

    expect($product->fresh()->stock)->toBe(50)
        ->and($movement1->product->id)->toBe($product->id)
        ->and($movement1->creator->id)->toBe($user->id)
        ->and($product->stockMovements)->toHaveCount(2);
});
