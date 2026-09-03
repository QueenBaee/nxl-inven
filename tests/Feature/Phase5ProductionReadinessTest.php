<?php

use App\Actions\StockOpname\ApproveStockOpnameAction;
use App\Actions\StockOpname\StartStockOpnameAction;
use App\Actions\StockOpname\SubmitStockOpnameCountAction;
use App\Enums\OpnameStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductType;
use App\Enums\SalesChannel;
use App\Enums\SettlementStatus;
use App\Enums\StockMovementType;
use App\Events\PayoutExecuted;
use App\Events\StockOpnameApproved;
use App\Livewire\PosCart;
use App\Models\ConsignmentSettlement;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('end-to-end multi-phase lifecycle test: master data -> inbound -> pos sale -> consignment settlement -> opname adjustment', function () {
    Event::fake([PayoutExecuted::class, StockOpnameApproved::class]);

    $owner = User::factory()->create(['role' => 'owner']);
    $cashier = User::factory()->create(['role' => 'staff']);

    // 1. Setup Master Data
    $supplier = Supplier::factory()->create(['name' => 'Fury Billiard Supply']);

    $regularProduct = Product::factory()->create([
        'name' => 'Taom Chalk Pyro',
        'sku' => 'E2E-CHALK-01',
        'type' => ProductType::Regular,
        'cost_price' => 250000.00,
        'selling_price' => 350000.00,
        'stock' => 0,
    ]);

    $consignmentProduct = Product::factory()->consignment($supplier)->create([
        'name' => 'Fury Carbon Shaft',
        'sku' => 'E2E-FURY-01',
        'type' => ProductType::Consignment,
        'cost_price' => 2000000.00,
        'selling_price' => 2800000.00,
        'stock' => 0,
    ]);

    // 2. Inbound Stock
    StockMovement::create([
        'product_id' => $regularProduct->id,
        'quantity' => 10,
        'type' => StockMovementType::In,
        'reference_note' => 'Initial regular purchase',
        'created_by' => $owner->id,
    ]);

    StockMovement::create([
        'product_id' => $consignmentProduct->id,
        'quantity' => 5,
        'type' => StockMovementType::In,
        'reference_note' => 'Consignment drop shipment',
        'created_by' => $owner->id,
    ]);

    expect($regularProduct->fresh()->stock)->toBe(10)
        ->and($consignmentProduct->fresh()->stock)->toBe(5);

    // 3. POS Checkout (Sold: 2 Regular + 1 Consignment)
    $this->actingAs($cashier);
    $cart = new PosCart;
    $cart->addItem($regularProduct->id, 2);
    $cart->addItem($consignmentProduct->id, 1);
    $cart->setChannel(SalesChannel::Offline);
    $cart->setPaymentMethod(PaymentMethod::Cash);

    $transaction = $cart->checkout();

    // Verify Transaction Details
    expect($transaction->total_amount)->toBe('3500000.00') // (2 * 350k) + (1 * 2.8M)
        ->and($regularProduct->fresh()->stock)->toBe(8)
        ->and($consignmentProduct->fresh()->stock)->toBe(4);

    // 4. Verify Consignment Settlement Creation
    $settlement = ConsignmentSettlement::where('transaction_item_id', $transaction->items()->where('product_id', $consignmentProduct->id)->first()->id)->first();
    expect($settlement)->not->toBeNull()
        ->and($settlement->amount)->toBe('2000000.00') // 1 * 2,000,000 cost_price
        ->and($settlement->status)->toBe(SettlementStatus::Unpaid)
        ->and($settlement->supplier_id)->toBe($supplier->id);

    // 5. Payout Consignment Settlement
    $ref = "PAYOUT-{$supplier->id}-".now()->format('Ymd').'-0001';
    $settlement->update([
        'status' => SettlementStatus::Paid,
        'payout_reference' => $ref,
        'paid_at' => now(),
    ]);
    PayoutExecuted::dispatch($supplier->id, $ref, (float) $settlement->amount, [$settlement->id]);

    expect($settlement->fresh()->status)->toBe(SettlementStatus::Paid)
        ->and($settlement->fresh()->paid_at)->not->toBeNull();

    Event::assertDispatched(PayoutExecuted::class, function (PayoutExecuted $event) use ($supplier, $settlement) {
        return $event->supplierId === $supplier->id
            && $event->totalAmount === 2000000.00
            && $event->settlementIds === [$settlement->id];
    });

    // 6. Stock Opname (Audit physical store inventory)
    $opname = StockOpname::create([
        'session_name' => 'Monthly Audit September 2026',
        'status' => OpnameStatus::Draft,
        'created_by' => $owner->id,
    ]);

    $startAction = app(StartStockOpnameAction::class);
    $startAction->execute($opname);

    expect($opname->fresh()->status)->toBe(OpnameStatus::InProgress)
        ->and($opname->items)->toHaveCount(2);

    // Enter Physical Counts: Regular has shortage of 1 (actual 7), Consignment has shortage of 1 (actual 3)
    $regItem = $opname->items()->where('product_id', $regularProduct->id)->first();
    $conItem = $opname->items()->where('product_id', $consignmentProduct->id)->first();

    $regItem->update(['physical_qty' => 7]);
    $conItem->update(['physical_qty' => 3]);

    // Submit Counting for Owner Review
    app(SubmitStockOpnameCountAction::class)->execute($opname, $cashier->id);

    // 7. Approve Opname & Synchronize Inventory
    $approveAction = app(ApproveStockOpnameAction::class);
    $approveAction->execute($opname, $owner->id);

    expect($opname->fresh()->status)->toBe(OpnameStatus::Completed)
        ->and($regularProduct->fresh()->stock)->toBe(7)
        ->and($consignmentProduct->fresh()->stock)->toBe(3);

    // Verify Adjustment Stock Movements linked by stock_opname_item_id
    $adjustments = StockMovement::where('type', StockMovementType::Adjustment)->get();
    expect($adjustments)->toHaveCount(2);

    // Verify Event Payload: Shop loss = 250,000 | Supplier liability = 2,000,000
    Event::assertDispatched(StockOpnameApproved::class, function (StockOpnameApproved $event) use ($opname, $owner) {
        return $event->stockOpnameId === $opname->id
            && $event->approvedBy === $owner->id
            && $event->totalShopLoss === 250000.00
            && $event->totalSupplierLiability === 2000000.00;
    });
});

test('pos checkout rolls back completely and rejects sale when product is frozen by active opname', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $cashier = User::factory()->create(['role' => 'staff']);

    $product = Product::factory()->create([
        'name' => 'Frozen Cue',
        'stock' => 5,
        'selling_price' => 500000.00,
    ]);

    $opname = StockOpname::create([
        'session_name' => 'Active Audit',
        'status' => OpnameStatus::Draft,
        'created_by' => $owner->id,
    ]);

    app(StartStockOpnameAction::class)->execute($opname);

    $this->actingAs($cashier);
    $cart = new PosCart;
    $cart->addItem($product->id, 1);
    $cart->setChannel(SalesChannel::Offline);
    $cart->setPaymentMethod(PaymentMethod::Cash);

    $result = $cart->checkout();
    expect($result)->toBeNull()
        ->and($cart->errorMessage)->toContain('Active Audit');

    // Ensure zero side effects
    expect(Transaction::count())->toBe(0)
        ->and(StockMovement::where('type', StockMovementType::Out)->count())->toBe(0)
        ->and($product->fresh()->stock)->toBe(5);
});

test('pos checkout rolls back completely when one item has insufficient stock', function () {
    $cashier = User::factory()->create(['role' => 'staff']);

    $productA = Product::factory()->create(['stock' => 10, 'selling_price' => 50000.00]);
    $productB = Product::factory()->create(['stock' => 1, 'selling_price' => 100000.00]);

    $this->actingAs($cashier);
    $cart = new PosCart;
    $cart->addItem($productA->id, 2);
    $cart->addItem($productB->id, 3); // Requests 3, but only 1 available!

    $result = $cart->checkout();
    expect($result)->toBeNull()
        ->and($cart->errorMessage)->toContain("Insufficient stock for product '{$productB->name}'");

    // Ensure complete rollback: productA stock was NOT deducted
    expect($productA->fresh()->stock)->toBe(10)
        ->and($productB->fresh()->stock)->toBe(1)
        ->and(Transaction::count())->toBe(0);
});

test('supplier payout rolls back atomically when an unexpected database failure occurs', function () {
    Event::fake([PayoutExecuted::class]);

    $supplier = Supplier::factory()->create();

    $settlement1 = ConsignmentSettlement::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => SettlementStatus::Unpaid,
        'amount' => 500000.00,
    ]);

    $settlement2 = ConsignmentSettlement::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => SettlementStatus::Unpaid,
        'amount' => 300000.00,
    ]);

    try {
        DB::transaction(function () use ($supplier) {
            $unpaid = ConsignmentSettlement::where('supplier_id', $supplier->id)
                ->where('status', SettlementStatus::Unpaid)
                ->lockForUpdate()
                ->get();

            $total = (float) $unpaid->sum('amount');
            $ids = $unpaid->pluck('id')->all();
            $ref = "PAYOUT-{$supplier->id}-".now()->format('Ymd').'-0001';

            ConsignmentSettlement::whereIn('id', $ids)->update([
                'status' => SettlementStatus::Paid,
                'payout_reference' => $ref,
                'paid_at' => now(),
            ]);

            PayoutExecuted::dispatch($supplier->id, $ref, $total, $ids);

            throw new RuntimeException('Simulated unexpected crash during bank integration');
        });
    } catch (RuntimeException $e) {
        // Expected
    }

    // Verify both settlements remained unpaid and event was NOT dispatched
    expect($settlement1->fresh()->status)->toBe(SettlementStatus::Unpaid)
        ->and($settlement2->fresh()->status)->toBe(SettlementStatus::Unpaid)
        ->and($settlement1->fresh()->paid_at)->toBeNull();

    Event::assertNotDispatched(PayoutExecuted::class);
});
