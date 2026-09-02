<?php

use App\Enums\OpnameStatus;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use App\Policies\StockOpnameItemPolicy;
use App\Policies\StockOpnamePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('staff can view list of sessions and open active in_progress session for counting', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $owner = User::factory()->create(['role' => 'owner']);

    $draft = StockOpname::create([
        'session_name' => 'Draft Sesi',
        'status' => OpnameStatus::Draft,
        'created_by' => $owner->id,
    ]);

    $active = StockOpname::create([
        'session_name' => 'Active Audit Sesi',
        'status' => OpnameStatus::InProgress,
        'created_by' => $owner->id,
    ]);

    $policy = new StockOpnamePolicy;

    expect($policy->viewAny($staff))->toBeTrue()
        ->and($policy->view($staff, $active))->toBeTrue()
        ->and($policy->view($staff, $draft))->toBeFalse(); // Staff cannot snoop on draft
});

test('staff sees Lanjutkan Hitung action on in_progress session but not owner actions', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $owner = User::factory()->create(['role' => 'owner']);

    $session = StockOpname::create([
        'session_name' => 'Store Audit September',
        'status' => OpnameStatus::InProgress,
        'created_by' => $owner->id,
    ]);

    $policy = new StockOpnamePolicy;

    expect($policy->start($staff, $session))->toBeFalse()
        ->and($policy->approve($staff, $session))->toBeFalse()
        ->and($policy->update($staff, $session))->toBeFalse()
        ->and($policy->delete($staff, $session))->toBeFalse()
        ->and($policy->create($staff))->toBeFalse();
});

test('staff can update physical_qty including zero on active session items', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $owner = User::factory()->create(['role' => 'owner']);
    $product = Product::factory()->create(['name' => 'Taom Chalk', 'sku' => 'CHALK-01', 'stock' => 10]);

    $session = StockOpname::create([
        'session_name' => 'Active Sesi',
        'status' => OpnameStatus::InProgress,
        'created_by' => $owner->id,
    ]);

    $item = StockOpnameItem::create([
        'stock_opname_id' => $session->id,
        'product_id' => $product->id,
        'system_qty' => 10,
        'cost_price_snapshot' => 100000.00,
        'is_consignment_snapshot' => false,
        'physical_qty' => null,
    ]);

    $itemPolicy = new StockOpnameItemPolicy;
    expect($itemPolicy->update($staff, $item))->toBeTrue();

    // Update physical_qty to 0 (valid zero count)
    $item->update(['physical_qty' => 0]);

    expect($item->fresh()->physical_qty)->toBe(0)
        ->and($item->fresh()->physical_qty !== null)->toBeTrue();

    // Update physical_qty to positive integer
    $item->update(['physical_qty' => 8]);
    expect($item->fresh()->physical_qty)->toBe(8);
});

test('staff query does not expose system_qty or cost_price_snapshot', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $owner = User::factory()->create(['role' => 'owner']);
    $product = Product::factory()->create(['stock' => 15, 'cost_price' => 500000.00]);

    $session = StockOpname::create([
        'session_name' => 'Blind Count Verification',
        'status' => OpnameStatus::InProgress,
        'created_by' => $owner->id,
    ]);

    $item = StockOpnameItem::create([
        'stock_opname_id' => $session->id,
        'product_id' => $product->id,
        'system_qty' => 15,
        'cost_price_snapshot' => 500000.00,
        'is_consignment_snapshot' => false,
        'physical_qty' => null,
    ]);

    $itemPolicy = new StockOpnameItemPolicy;

    // Staff cannot view audit details
    expect($itemPolicy->viewAuditDetails($staff))->toBeFalse()
        ->and($itemPolicy->viewAuditDetails($owner))->toBeTrue();
});

test('completed session locks physical_qty modifications for everyone', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $owner = User::factory()->create(['role' => 'owner']);
    $product = Product::factory()->create();

    $session = StockOpname::create([
        'session_name' => 'Completed Sesi',
        'status' => OpnameStatus::Completed,
        'created_by' => $owner->id,
        'approved_by' => $owner->id,
    ]);

    $item = StockOpnameItem::create([
        'stock_opname_id' => $session->id,
        'product_id' => $product->id,
        'system_qty' => 10,
        'cost_price_snapshot' => 50000.00,
        'is_consignment_snapshot' => false,
        'physical_qty' => 10,
    ]);

    $itemPolicy = new StockOpnameItemPolicy;

    expect($itemPolicy->update($staff, $item))->toBeFalse()
        ->and($itemPolicy->update($owner, $item))->toBeFalse();
});

test('owner can access review mode with variance calculations', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $product = Product::factory()->create(['stock' => 10, 'cost_price' => 100000.00]);

    $session = StockOpname::create([
        'session_name' => 'Owner Review Sesi',
        'status' => OpnameStatus::InProgress,
        'created_by' => $owner->id,
    ]);

    $item = StockOpnameItem::create([
        'stock_opname_id' => $session->id,
        'product_id' => $product->id,
        'system_qty' => 10,
        'cost_price_snapshot' => 100000.00,
        'is_consignment_snapshot' => false,
        'physical_qty' => 8,
    ]);

    expect($item->variance)->toBe(-2)
        ->and((float) $item->loss_value)->toBe(200000.00)
        ->and($item->varianceType())->toBe('shop_loss');
});
