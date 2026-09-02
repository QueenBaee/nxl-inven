<?php

use App\Enums\PaymentMethod;
use App\Enums\ProductType;
use App\Enums\SalesChannel;
use App\Enums\SettlementStatus;
use App\Events\PayoutExecuted;
use App\Livewire\PosCart;
use App\Models\ConsignmentSettlement;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Observers\TransactionItemObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('consignment transaction item automatically creates settlement record with snapshot data', function () {
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->consignment($supplier)->create([
        'cost_price' => 75000.00,
        'selling_price' => 100000.00,
        'stock' => 10,
    ]);

    $transaction = Transaction::factory()->create();

    $item = $transaction->items()->create([
        'product_id' => $product->id,
        'quantity' => 3,
        'price' => $product->selling_price,
        'cost_price' => $product->cost_price,
        'is_consignment' => true,
    ]);

    $settlement = ConsignmentSettlement::where('transaction_item_id', $item->id)->first();

    expect($settlement)->not->toBeNull()
        ->and($settlement->supplier_id)->toBe($supplier->id)
        ->and((float) $settlement->amount)->toBe(225000.00) // 75,000 * 3
        ->and($settlement->status)->toBe(SettlementStatus::Unpaid)
        ->and($settlement->payout_reference)->toBeNull()
        ->and($settlement->paid_at)->toBeNull()
        ->and($settlement->supplier->id)->toBe($supplier->id)
        ->and($settlement->transactionItem->id)->toBe($item->id);
});

test('regular product transaction item does not create settlement record', function () {
    $product = Product::factory()->create([
        'type' => ProductType::Regular,
        'cost_price' => 20000.00,
        'selling_price' => 30000.00,
    ]);

    $transaction = Transaction::factory()->create();

    $item = $transaction->items()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'price' => $product->selling_price,
        'cost_price' => $product->cost_price,
        'is_consignment' => false,
    ]);

    $settlement = ConsignmentSettlement::where('transaction_item_id', $item->id)->first();
    expect($settlement)->toBeNull();
});

test('settlement creation is strictly idempotent and does not create duplicates', function () {
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->consignment($supplier)->create([
        'cost_price' => 50000.00,
        'stock' => 10,
    ]);

    $transaction = Transaction::factory()->create();

    $item = $transaction->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => 80000.00,
        'cost_price' => 50000.00,
        'is_consignment' => true,
    ]);

    expect(ConsignmentSettlement::where('transaction_item_id', $item->id)->count())->toBe(1);

    // Trigger observer logic again manually to simulate a retry
    (new TransactionItemObserver)->created($item);

    expect(ConsignmentSettlement::where('transaction_item_id', $item->id)->count())->toBe(1);
});

test('pos checkout flow end-to-end automatically creates consignment settlements', function () {
    $user = User::factory()->create();
    Auth::login($user);

    $supplier = Supplier::factory()->create();
    $consignmentProduct = Product::factory()->consignment($supplier)->create([
        'name' => 'Consignment Cue',
        'selling_price' => 500000.00,
        'cost_price' => 400000.00,
        'stock' => 5,
    ]);

    $cart = new PosCart;
    $cart->addItem($consignmentProduct->id, 2);
    $cart->setPaymentMethod(PaymentMethod::Cash);
    $cart->setChannel(SalesChannel::Offline);

    $transaction = $cart->checkout();

    expect(ConsignmentSettlement::count())->toBe(1);

    $settlement = ConsignmentSettlement::first();
    expect($settlement->supplier_id)->toBe($supplier->id)
        ->and((float) $settlement->amount)->toBe(800000.00) // 400,000 * 2
        ->and($settlement->status)->toBe(SettlementStatus::Unpaid);
});

test('batch payout updates settlements atomically and dispatches PayoutExecuted event', function () {
    Event::fake([PayoutExecuted::class]);

    $supplier = Supplier::factory()->create();
    $transaction = Transaction::factory()->create();
    $product = Product::factory()->consignment($supplier)->create(['cost_price' => 100000.00]);

    $item1 = $transaction->items()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'price' => 150000.00,
        'cost_price' => 100000.00,
        'is_consignment' => true,
    ]);

    $item2 = $transaction->items()->create([
        'product_id' => $product->id,
        'quantity' => 3,
        'price' => 150000.00,
        'cost_price' => 100000.00,
        'is_consignment' => true,
    ]);

    $settlements = ConsignmentSettlement::where('supplier_id', $supplier->id)->get();
    expect($settlements)->toHaveCount(2);

    // Execute payout logic
    $supplierId = $supplier->id;
    $today = now()->format('Ymd');
    $expectedPrefix = "PAYOUT-{$supplierId}-{$today}-0001";

    DB::transaction(function () use ($supplierId, $expectedPrefix) {
        $unpaid = ConsignmentSettlement::where('supplier_id', $supplierId)
            ->where('status', SettlementStatus::Unpaid)
            ->lockForUpdate()
            ->get();

        $total = (float) $unpaid->sum('amount');
        $ids = $unpaid->pluck('id')->all();

        ConsignmentSettlement::whereIn('id', $ids)
            ->update([
                'status' => SettlementStatus::Paid,
                'payout_reference' => $expectedPrefix,
                'paid_at' => now(),
            ]);

        PayoutExecuted::dispatch($supplierId, $expectedPrefix, $total, $ids);
    });

    $updatedSettlements = ConsignmentSettlement::where('supplier_id', $supplier->id)->get();
    foreach ($updatedSettlements as $s) {
        expect($s->status)->toBe(SettlementStatus::Paid)
            ->and($s->payout_reference)->toBe($expectedPrefix)
            ->and($s->paid_at)->not->toBeNull();
    }

    Event::assertDispatched(PayoutExecuted::class, function (PayoutExecuted $event) use ($supplierId, $expectedPrefix) {
        return $event->supplierId === $supplierId
            && $event->payoutReference === $expectedPrefix
            && $event->totalAmount === 500000.00 // (2 * 100k) + (3 * 100k)
            && count($event->settlementIds) === 2;
    });
});
