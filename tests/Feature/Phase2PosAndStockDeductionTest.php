<?php

use App\Enums\PaymentMethod;
use App\Enums\ProductType;
use App\Enums\SalesChannel;
use App\Enums\StockMovementType;
use App\Enums\TransactionStatus;
use App\Livewire\PosCart;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

test('isReceivable accessor accurately differentiates offline vs marketplace channels', function () {
    $offlineTx = Transaction::factory()->make([
        'channel' => SalesChannel::Offline,
    ]);
    expect($offlineTx->isReceivable())->toBeFalse();

    $shopeeTx = Transaction::factory()->make([
        'channel' => SalesChannel::Shopee,
    ]);
    expect($shopeeTx->isReceivable())->toBeTrue();

    $tokopediaTx = Transaction::factory()->make([
        'channel' => SalesChannel::Tokopedia,
    ]);
    expect($tokopediaTx->isReceivable())->toBeTrue();
});

test('transaction items compute subtotal dynamically and retain snapshots', function () {
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->consignment($supplier)->create([
        'selling_price' => 25000.00,
        'cost_price' => 18000.00,
        'stock' => 10,
    ]);

    $transaction = Transaction::create([
        'invoice_number' => 'INV-20260901-0001',
        'total_amount' => 50000.00,
        'payment_method' => PaymentMethod::Cash,
        'channel' => SalesChannel::Offline,
        'status' => TransactionStatus::Completed,
    ]);

    $item = $transaction->items()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'price' => $product->selling_price,
        'cost_price' => $product->cost_price,
        'is_consignment' => true,
    ]);

    expect($item->subtotal)->toBe('50000.00')
        ->and($item->is_consignment)->toBeTrue()
        ->and($item->price)->toBe('25000.00')
        ->and($item->cost_price)->toBe('18000.00');
});

test('stock movement observer decrements product stock for type out', function () {
    $product = Product::factory()->create(['stock' => 50]);

    StockMovement::create([
        'product_id' => $product->id,
        'quantity' => 12,
        'type' => StockMovementType::Out,
        'reference_note' => 'Sale INV-20260901-0001',
    ]);

    expect($product->fresh()->stock)->toBe(38);
});

test('pos cart can add, update, remove items and set channels', function () {
    $product1 = Product::factory()->create(['selling_price' => 10000.00, 'stock' => 10]);
    $product2 = Product::factory()->create(['selling_price' => 20000.00, 'stock' => 10]);

    $component = new PosCart;
    $component->addItem($product1->id, 2);
    $component->addItem($product2->id, 1);

    expect($component->items)->toHaveCount(2)
        ->and($component->items[$product1->id]['quantity'])->toBe(2)
        ->and($component->getCartTotal())->toBe(40000.0);

    $component->updateQuantity($product1->id, 5);
    expect($component->items[$product1->id]['quantity'])->toBe(5);

    $component->removeItem($product2->id);
    expect($component->items)->toHaveCount(1)
        ->and(isset($component->items[$product2->id]))->toBeFalse();

    $component->setPaymentMethod(PaymentMethod::Qris);
    expect($component->paymentMethod)->toBe('qris');

    $component->setChannel(SalesChannel::Shopee);
    expect($component->channel)->toBe('shopee');
});

test('checkout successfully creates transaction, items, stock movements, and deducts stock', function () {
    $user = User::factory()->create();
    Auth::login($user);

    $supplier = Supplier::factory()->create();
    $regularProduct = Product::factory()->create([
        'name' => 'Billiard Chalk',
        'selling_price' => 15000.00,
        'cost_price' => 8000.00,
        'stock' => 20,
        'type' => ProductType::Regular,
    ]);

    $consignmentProduct = Product::factory()->consignment($supplier)->create([
        'name' => 'Custom Shaft',
        'selling_price' => 150000.00,
        'cost_price' => 120000.00,
        'stock' => 5,
        'type' => ProductType::Consignment,
    ]);

    $cart = new PosCart;
    $cart->addItem($regularProduct->id, 3);      // 3 * 15,000 = 45,000
    $cart->addItem($consignmentProduct->id, 2);  // 2 * 150,000 = 300,000
    $cart->setPaymentMethod(PaymentMethod::Transfer);
    $cart->setChannel(SalesChannel::Tokopedia);

    $transaction = $cart->checkout();

    $expectedDate = now()->format('Ymd');
    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transaction->invoice_number)->toBe("INV-{$expectedDate}-0001")
        ->and((float) $transaction->total_amount)->toBe(345000.00)
        ->and($transaction->payment_method)->toBe(PaymentMethod::Transfer)
        ->and($transaction->channel)->toBe(SalesChannel::Tokopedia)
        ->and($transaction->status)->toBe(TransactionStatus::Completed)
        ->and($transaction->created_by)->toBe($user->id)
        ->and($transaction->isReceivable())->toBeTrue();

    // Verify transaction items
    expect($transaction->items)->toHaveCount(2);

    $chalkItem = $transaction->items()->where('product_id', $regularProduct->id)->first();
    expect($chalkItem->quantity)->toBe(3)
        ->and((float) $chalkItem->price)->toBe(15000.00)
        ->and((float) $chalkItem->cost_price)->toBe(8000.00)
        ->and($chalkItem->is_consignment)->toBeFalse()
        ->and($chalkItem->subtotal)->toBe('45000.00');

    $shaftItem = $transaction->items()->where('product_id', $consignmentProduct->id)->first();
    expect($shaftItem->quantity)->toBe(2)
        ->and((float) $shaftItem->price)->toBe(150000.00)
        ->and((float) $shaftItem->cost_price)->toBe(120000.00)
        ->and($shaftItem->is_consignment)->toBeTrue()
        ->and($shaftItem->subtotal)->toBe('300000.00');

    // Verify stock deductions through StockMovements
    expect($regularProduct->fresh()->stock)->toBe(17) // 20 - 3
        ->and($consignmentProduct->fresh()->stock)->toBe(3); // 5 - 2

    $movements = StockMovement::where('type', StockMovementType::Out)->get();
    expect($movements)->toHaveCount(2)
        ->and($movements->first()->reference_note)->toBe("Sale {$transaction->invoice_number}");

    // Verify consecutive checkout generates next invoice number
    $cart2 = new PosCart;
    $cart2->addItem($regularProduct->id, 1);
    $tx2 = $cart2->checkout();
    expect($tx2->invoice_number)->toBe("INV-{$expectedDate}-0002");
});

test('checkout throws InsufficientStockException and rolls back transaction when stock is inadequate', function () {
    $user = User::factory()->create();
    Auth::login($user);

    $product = Product::factory()->create([
        'name' => 'Limited Glove',
        'selling_price' => 50000.00,
        'cost_price' => 30000.00,
        'stock' => 2,
    ]);

    $cart = new PosCart;
    $cart->addItem($product->id, 5); // Requests 5, only 2 available

    $result = $cart->checkout();
    expect($result)->toBeNull()
        ->and($cart->errorMessage)->toContain("Insufficient stock for product 'Limited Glove'");

    // Ensure complete rollback
    expect(Transaction::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0)
        ->and($product->fresh()->stock)->toBe(2);
});
