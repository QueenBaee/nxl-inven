<?php

use App\Actions\StockOpname\StartStockOpnameAction;
use App\Enums\OpnameStatus;
use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Livewire\PosCart;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\Transaction;
use App\Models\User;
use App\Policies\TransactionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| 1. POS UI & Component Usability Tests
|--------------------------------------------------------------------------
*/

test('pos component renders product catalog and supports real-time search', function () {
    Product::factory()->create(['name' => 'Taom Chalk Pyro', 'sku' => 'CHALK-01', 'stock' => 10, 'selling_price' => 350000.00]);
    Product::factory()->create(['name' => 'Fury Carbon Shaft', 'sku' => 'FURY-01', 'stock' => 5, 'selling_price' => 2000000.00]);

    Livewire::test(PosCart::class)
        ->assertSee('Taom Chalk Pyro')
        ->assertSee('Fury Carbon Shaft')
        ->set('searchQuery', 'Taom')
        ->assertSee('Taom Chalk Pyro')
        ->assertDontSee('Fury Carbon Shaft');
});

test('pos component allows adding, incrementing, decrementing, and removing items', function () {
    $product = Product::factory()->create(['name' => 'Billiard Glove Pro', 'sku' => 'GLV-01', 'stock' => 10, 'selling_price' => 50000.00]);

    Livewire::test(PosCart::class)
        ->call('addItem', $product->id, 1)
        ->assertSee('1 item(s) terpilih')
        ->call('incrementQuantity', $product->id)
        ->assertSee('2 item(s) terpilih')
        ->call('decrementQuantity', $product->id)
        ->assertSee('1 item(s) terpilih')
        ->call('removeItem', $product->id)
        ->assertSee('0 item(s) terpilih')
        ->assertSee('Keranjang masih kosong');
});

test('pos component prevents checkout on empty cart', function () {
    $cart = new PosCart;

    $result = $cart->checkout();
    expect($result)->toBeNull()
        ->and($cart->errorMessage)->toBe('Cannot checkout an empty cart.');
});

test('pos component provides friendly feedback when stock is insufficient', function () {
    $product = Product::factory()->create(['name' => 'Limited Cue', 'stock' => 2]);

    $cart = new PosCart;
    $cart->addItem($product->id, 2);

    // Concurrently drop stock or request more
    $product->update(['stock' => 1]);

    $result = $cart->checkout();
    expect($result)->toBeNull()
        ->and($cart->errorMessage)->toContain("Insufficient stock for product '{$product->name}'");
});

test('pos component provides friendly feedback when product is frozen by stock opname', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $product = Product::factory()->create(['name' => 'Frozen Chalk', 'stock' => 10]);

    $opname = StockOpname::create([
        'session_name' => 'Audit Toko Utama',
        'status' => OpnameStatus::Draft,
        'created_by' => $owner->id,
    ]);

    app(StartStockOpnameAction::class)->execute($opname);

    $cart = new PosCart;
    $cart->items = [
        $product->id => [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => (string) $product->selling_price,
            'quantity' => 1,
            'stock' => 10,
            'is_consignment' => false,
        ],
    ];

    $result = $cart->checkout();
    expect($result)->toBeNull()
        ->and($cart->errorMessage)->toContain('Audit Toko Utama');
});

test('successful pos checkout clears cart and sets success message with invoice details', function () {
    $user = User::factory()->create();
    Auth::login($user);

    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 100000.00]);

    $cart = new PosCart;
    $cart->addItem($product->id, 2);
    $cart->setChannel(SalesChannel::Offline);
    $cart->setPaymentMethod(PaymentMethod::Cash);

    $tx = $cart->checkout();

    expect($tx)->toBeInstanceOf(Transaction::class)
        ->and($cart->items)->toBeEmpty()
        ->and($cart->successMessage)->toContain($tx->invoice_number)
        ->and($cart->lastInvoiceNumber)->toBe($tx->invoice_number);
});

/*
|--------------------------------------------------------------------------
| 2. Security & Immutability UI Tests
|--------------------------------------------------------------------------
*/

test('completed transaction policy strictly denies deletion and modification', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $staff = User::factory()->create(['role' => 'staff']);
    $transaction = Transaction::factory()->create();

    $policy = new TransactionPolicy;

    expect($policy->update($owner, $transaction))->toBeFalse()
        ->and($policy->update($staff, $transaction))->toBeFalse()
        ->and($policy->delete($owner, $transaction))->toBeFalse()
        ->and($policy->delete($staff, $transaction))->toBeFalse();
});
