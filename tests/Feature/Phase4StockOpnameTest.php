<?php

use App\Actions\StockOpname\ApproveStockOpnameAction;
use App\Actions\StockOpname\StartStockOpnameAction;
use App\Enums\OpnameStatus;
use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Events\StockOpnameApproved;
use App\Exceptions\StockOpnameInProgressException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Supplier;
use App\Models\User;
use App\Policies\StockOpnameItemPolicy;
use App\Policies\StockOpnamePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| 1. Start Opname Tests
|--------------------------------------------------------------------------
*/

test('owner can start opname, accurately snapshotting active products and freezing mutations', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $supplier = Supplier::factory()->create();

    $regular = Product::factory()->create([
        'name' => 'Billiard Chalk Box',
        'sku' => 'CHK-001',
        'stock' => 25,
        'cost_price' => 12500.00,
        'type' => ProductType::Regular,
    ]);

    $consignment = Product::factory()->consignment($supplier)->create([
        'name' => 'Custom Carbon Shaft',
        'sku' => 'SFT-001',
        'stock' => 10,
        'cost_price' => 850000.00,
        'type' => ProductType::Consignment,
    ]);

    $opname = StockOpname::create([
        'session_name' => 'End of Month Audit - Sept 2026',
        'status' => OpnameStatus::Draft,
        'created_by' => $owner->id,
    ]);

    $action = app(StartStockOpnameAction::class);
    $startedOpname = $action->execute($opname);

    expect($startedOpname->status)->toBe(OpnameStatus::InProgress)
        ->and($startedOpname->items)->toHaveCount(2);

    $regItem = $startedOpname->items()->where('product_id', $regular->id)->first();
    expect($regItem->system_qty)->toBe(25)
        ->and((float) $regItem->cost_price_snapshot)->toBe(12500.00)
        ->and($regItem->is_consignment_snapshot)->toBeFalse()
        ->and($regItem->physical_qty)->toBeNull();

    $conItem = $startedOpname->items()->where('product_id', $consignment->id)->first();
    expect($conItem->system_qty)->toBe(10)
        ->and((float) $conItem->cost_price_snapshot)->toBe(850000.00)
        ->and($conItem->is_consignment_snapshot)->toBeTrue()
        ->and($conItem->physical_qty)->toBeNull();
});

test('only one in_progress opname session may exist globally at a time', function () {
    $owner = User::factory()->create(['role' => 'owner']);

    // Existing active session
    StockOpname::create([
        'session_name' => 'Already Running Audit',
        'status' => OpnameStatus::InProgress,
        'created_by' => $owner->id,
    ]);

    $newDraft = StockOpname::create([
        'session_name' => 'Concurrent Attempt Session',
        'status' => OpnameStatus::Draft,
        'created_by' => $owner->id,
    ]);

    $action = app(StartStockOpnameAction::class);

    expect(function () use ($action, $newDraft) {
        $action->execute($newDraft);
    })->toThrow(ValidationException::class, 'Another Stock Opname session is currently in progress.');
});

test('double start on the same session is rejected and does not duplicate items', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    Product::factory()->create(['stock' => 5]);

    $opname = StockOpname::create([
        'session_name' => 'Single Start Session',
        'status' => OpnameStatus::Draft,
        'created_by' => $owner->id,
    ]);

    $action = app(StartStockOpnameAction::class);
    $action->execute($opname);

    // Attempting to execute again on now in_progress session
    expect(function () use ($action, $opname) {
        $action->execute($opname->fresh());
    })->toThrow(ValidationException::class, "Cannot start session: Current status is 'in_progress', but 'draft' is required.");

    expect(StockOpnameItem::where('stock_opname_id', $opname->id)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| 2. Freeze Guard Tests
|--------------------------------------------------------------------------
*/

test('freeze guard strictly rejects stock in and out movements for products in active opname', function () {
    $user = User::factory()->create();
    $frozenProduct = Product::factory()->create(['stock' => 30]);

    $opname = StockOpname::create([
        'session_name' => 'Active Freeze Test',
        'status' => OpnameStatus::InProgress,
        'created_by' => $user->id,
    ]);

    $opname->items()->create([
        'product_id' => $frozenProduct->id,
        'system_qty' => 30,
        'cost_price_snapshot' => 20000.00,
        'is_consignment_snapshot' => false,
    ]);

    // Test Inbound blocked
    expect(function () use ($frozenProduct, $user) {
        StockMovement::create([
            'product_id' => $frozenProduct->id,
            'quantity' => 10,
            'type' => StockMovementType::In,
            'created_by' => $user->id,
        ]);
    })->toThrow(StockOpnameInProgressException::class, "Stock mutations are frozen for product ID {$frozenProduct->id}");

    // Test Outbound (POS sale) blocked
    expect(function () use ($frozenProduct, $user) {
        StockMovement::create([
            'product_id' => $frozenProduct->id,
            'quantity' => 2,
            'type' => StockMovementType::Out,
            'created_by' => $user->id,
        ]);
    })->toThrow(StockOpnameInProgressException::class, "Stock mutations are frozen for product ID {$frozenProduct->id}");

    expect($frozenProduct->fresh()->stock)->toBe(30);
});

test('products not participating in active opname can mutate normally', function () {
    $user = User::factory()->create();
    $frozenProduct = Product::factory()->create(['stock' => 10]);
    $unfrozenProduct = Product::factory()->create(['stock' => 10]);

    $opname = StockOpname::create([
        'session_name' => 'Partial Audit',
        'status' => OpnameStatus::InProgress,
        'created_by' => $user->id,
    ]);

    $opname->items()->create([
        'product_id' => $frozenProduct->id,
        'system_qty' => 10,
        'cost_price_snapshot' => 15000.00,
        'is_consignment_snapshot' => false,
    ]);

    // Unfrozen product mutation succeeds
    StockMovement::create([
        'product_id' => $unfrozenProduct->id,
        'quantity' => 5,
        'type' => StockMovementType::In,
        'created_by' => $user->id,
    ]);

    expect($unfrozenProduct->fresh()->stock)->toBe(15);
});

/*
|--------------------------------------------------------------------------
| 3. Blind Count & Authorization Tests
|--------------------------------------------------------------------------
*/

test('policies enforce blind count security for staff vs owner roles', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $staff = User::factory()->create(['role' => 'staff']);

    $opnamePolicy = new StockOpnamePolicy;
    $itemPolicy = new StockOpnameItemPolicy;

    $draftSession = StockOpname::factory()->make(['status' => OpnameStatus::Draft]);
    $activeSession = StockOpname::factory()->make(['status' => OpnameStatus::InProgress]);

    // Opname Policy checks
    expect($opnamePolicy->create($owner))->toBeTrue()
        ->and($opnamePolicy->create($staff))->toBeFalse()
        ->and($opnamePolicy->start($owner, $draftSession))->toBeTrue()
        ->and($opnamePolicy->start($staff, $draftSession))->toBeFalse()
        ->and($opnamePolicy->approve($owner, $activeSession))->toBeTrue()
        ->and($opnamePolicy->approve($staff, $activeSession))->toBeFalse();

    // Item Policy blind count checks
    expect($itemPolicy->viewAuditDetails($owner))->toBeTrue()
        ->and($itemPolicy->viewAuditDetails($staff))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| 4. Variance, Loss Value, and VarianceType Accessors Tests
|--------------------------------------------------------------------------
*/

test('variance, loss_value, and varianceType compute correctly without database column storage', function () {
    $regularItem = new StockOpnameItem([
        'system_qty' => 20,
        'cost_price_snapshot' => 15000.00,
        'physical_qty' => 16, // Shortage of 4
        'is_consignment_snapshot' => false,
    ]);

    expect($regularItem->variance)->toBe(-4)
        ->and($regularItem->loss_value)->toBe(60000.0) // 4 * 15,000
        ->and($regularItem->varianceType())->toBe('shop_loss');

    $consignmentItem = new StockOpnameItem([
        'system_qty' => 8,
        'cost_price_snapshot' => 120000.00,
        'physical_qty' => 5, // Shortage of 3
        'is_consignment_snapshot' => true,
    ]);

    expect($consignmentItem->variance)->toBe(-3)
        ->and($consignmentItem->loss_value)->toBe(360000.0) // 3 * 120,000
        ->and($consignmentItem->varianceType())->toBe('supplier_liability');

    $surplusItem = new StockOpnameItem([
        'system_qty' => 10,
        'cost_price_snapshot' => 50000.00,
        'physical_qty' => 14, // Surplus of 4
        'is_consignment_snapshot' => false,
    ]);

    expect($surplusItem->variance)->toBe(4)
        ->and($surplusItem->loss_value)->toBe(0.0);

    $uncountedItem = new StockOpnameItem([
        'system_qty' => 10,
        'cost_price_snapshot' => 50000.00,
        'physical_qty' => null,
        'is_consignment_snapshot' => false,
    ]);

    expect($uncountedItem->variance)->toBeNull()
        ->and($uncountedItem->loss_value)->toBe(0.0);
});

/*
|--------------------------------------------------------------------------
| 5. Approval & Direct Stock Synchronization Tests
|--------------------------------------------------------------------------
*/

test('approval is blocked with descriptive message if uncounted items remain', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $product1 = Product::factory()->create(['name' => 'Kamui Tip M', 'sku' => 'TIP-001', 'stock' => 10]);
    $product2 = Product::factory()->create(['name' => 'Mezz Chalk', 'sku' => 'CHK-002', 'stock' => 5]);

    $opname = StockOpname::create([
        'session_name' => 'Incomplete Audit Session',
        'status' => OpnameStatus::InProgress,
        'created_by' => $owner->id,
    ]);

    $opname->items()->create([
        'product_id' => $product1->id,
        'system_qty' => 10,
        'cost_price_snapshot' => 100000.00,
        'physical_qty' => 10, // Counted
        'is_consignment_snapshot' => false,
    ]);

    $opname->items()->create([
        'product_id' => $product2->id,
        'system_qty' => 5,
        'cost_price_snapshot' => 30000.00,
        'physical_qty' => null, // UNCOUNTED
        'is_consignment_snapshot' => false,
    ]);

    $opname->update(['counting_completed_at' => now(), 'counting_completed_by' => $owner->id]);

    $action = app(ApproveStockOpnameAction::class);

    expect(function () use ($action, $opname, $owner) {
        $action->execute($opname, $owner->id);
    })->toThrow(ValidationException::class, 'Mezz Chalk (CHK-002)');

    expect($opname->fresh()->status)->toBe(OpnameStatus::InProgress);
});

test('approval reconciles stock directly to physical count and categorizes losses in event payload', function () {
    Event::fake([StockOpnameApproved::class]);

    $owner = User::factory()->create(['role' => 'owner']);
    $supplier = Supplier::factory()->create();

    $regProduct = Product::factory()->create([
        'name' => 'Master Chalk Blue',
        'stock' => 20,
        'cost_price' => 10000.00,
        'type' => ProductType::Regular,
    ]);

    $conProduct = Product::factory()->consignment($supplier)->create([
        'name' => 'Consignment Glove Pro',
        'stock' => 12,
        'cost_price' => 50000.00,
        'type' => ProductType::Consignment,
    ]);

    $surplusProduct = Product::factory()->create([
        'name' => 'Cue Joint Protector',
        'stock' => 8,
        'cost_price' => 25000.00,
        'type' => ProductType::Regular,
    ]);

    $opname = StockOpname::create([
        'session_name' => 'Year-End Reconciliation',
        'status' => OpnameStatus::InProgress,
        'created_by' => $owner->id,
    ]);

    $item1 = $opname->items()->create([
        'product_id' => $regProduct->id,
        'system_qty' => 20,
        'cost_price_snapshot' => 10000.00,
        'physical_qty' => 13, // Shortage of 7 (Shop Loss = 7 * 10,000 = 70,000)
        'is_consignment_snapshot' => false,
    ]);

    $item2 = $opname->items()->create([
        'product_id' => $conProduct->id,
        'system_qty' => 12,
        'cost_price_snapshot' => 50000.00,
        'physical_qty' => 9, // Shortage of 3 (Supplier Liability = 3 * 50,000 = 150,000)
        'is_consignment_snapshot' => true,
    ]);

    $item3 = $opname->items()->create([
        'product_id' => $surplusProduct->id,
        'system_qty' => 8,
        'cost_price_snapshot' => 25000.00,
        'physical_qty' => 11, // Surplus of 3 (No loss)
        'is_consignment_snapshot' => false,
    ]);

    $opname->update(['counting_completed_at' => now(), 'counting_completed_by' => $owner->id]);

    $action = app(ApproveStockOpnameAction::class);
    $completedSession = $action->execute($opname, $owner->id);

    expect($completedSession->status)->toBe(OpnameStatus::Completed)
        ->and($completedSession->approved_by)->toBe($owner->id);

    // Verify Direct Stock Synchronization (Final stock strictly equals physical count)
    expect($regProduct->fresh()->stock)->toBe(13)
        ->and($conProduct->fresh()->stock)->toBe(9)
        ->and($surplusProduct->fresh()->stock)->toBe(11);

    // Verify Adjustment StockMovement Records
    $movements = StockMovement::where('type', StockMovementType::Adjustment)->get();
    expect($movements)->toHaveCount(3);

    $regMovement = $movements->where('product_id', $regProduct->id)->first();
    expect($regMovement->quantity)->toBe(7)
        ->and($regMovement->stock_opname_item_id)->toBe($item1->id)
        ->and($regMovement->reference_note)->toBe("Stock Opname Adjustment - {$opname->session_name}");

    // Verify Event Payload
    Event::assertDispatched(StockOpnameApproved::class, function (StockOpnameApproved $event) use ($opname, $owner) {
        return $event->stockOpnameId === $opname->id
            && $event->approvedBy === $owner->id
            && $event->totalShopLoss === 70000.00
            && $event->totalSupplierLiability === 150000.00
            && count($event->itemIds) === 3;
    });
});

test('double approval is rejected atomically', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $product = Product::factory()->create(['stock' => 10]);

    $opname = StockOpname::create([
        'session_name' => 'Single Approval Test',
        'status' => OpnameStatus::InProgress,
        'created_by' => $owner->id,
    ]);

    $opname->items()->create([
        'product_id' => $product->id,
        'system_qty' => 10,
        'cost_price_snapshot' => 10000.00,
        'physical_qty' => 10,
        'is_consignment_snapshot' => false,
    ]);

    $opname->update(['counting_completed_at' => now(), 'counting_completed_by' => $owner->id]);

    $action = app(ApproveStockOpnameAction::class);
    $action->execute($opname, $owner->id);

    expect(function () use ($action, $opname, $owner) {
        $action->execute($opname->fresh(), $owner->id);
    })->toThrow(ValidationException::class, "Cannot approve session: Current status is 'completed', but 'in_progress' is required.");
});

test('transaction failure during approval rolls back all stock changes and prevents event dispatch', function () {
    Event::fake([StockOpnameApproved::class]);

    $owner = User::factory()->create(['role' => 'owner']);
    $product = Product::factory()->create(['stock' => 20]);

    $opname = StockOpname::create([
        'session_name' => 'Failing Opname Session',
        'status' => OpnameStatus::InProgress,
        'created_by' => $owner->id,
    ]);

    $opname->items()->create([
        'product_id' => $product->id,
        'system_qty' => 20,
        'cost_price_snapshot' => 10000.00,
        'physical_qty' => 15,
        'is_consignment_snapshot' => false,
    ]);

    $opname->update(['counting_completed_at' => now(), 'counting_completed_by' => $owner->id]);

    // Force an unexpected exception during execution to test rollback
    try {
        DB::transaction(function () use ($opname, $owner) {
            $action = app(ApproveStockOpnameAction::class);
            $action->execute($opname, $owner->id);
            throw new RuntimeException('Simulated unexpected system failure before commit');
        });
    } catch (RuntimeException $e) {
        // Expected simulation exception
    }

    // Verify complete rollback: stock untouched, no movements created, opname remains in_progress
    expect($product->fresh()->stock)->toBe(20)
        ->and(StockMovement::where('type', StockMovementType::Adjustment)->count())->toBe(0)
        ->and($opname->fresh()->status)->toBe(OpnameStatus::InProgress);

    Event::assertNotDispatched(StockOpnameApproved::class);
});
