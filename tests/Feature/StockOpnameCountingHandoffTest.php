<?php

use App\Actions\StockOpname\ApproveStockOpnameAction;
use App\Actions\StockOpname\ReopenStockOpnameCountAction;
use App\Actions\StockOpname\SubmitStockOpnameCountAction;
use App\Enums\OpnameStatus;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\User;
use App\Policies\StockOpnameItemPolicy;
use App\Policies\StockOpnamePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| 1. Staff Submission & Validation Tests
|--------------------------------------------------------------------------
*/

test('staff cannot submit count if any item remains uncounted (physical_qty is null)', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $owner = User::factory()->create(['role' => 'owner']);
    $product1 = Product::factory()->create(['name' => 'Taom Chalk', 'sku' => 'CHK-01']);
    $product2 = Product::factory()->create(['name' => 'Kamui Tip', 'sku' => 'TIP-01']);

    $session = StockOpname::create([
        'session_name' => 'Audit Sesi 1',
        'status' => OpnameStatus::InProgress,
        'created_by' => $owner->id,
    ]);

    $session->items()->create([
        'product_id' => $product1->id,
        'system_qty' => 10,
        'cost_price_snapshot' => 100000.00,
        'is_consignment_snapshot' => false,
        'physical_qty' => 10,
    ]);

    $session->items()->create([
        'product_id' => $product2->id,
        'system_qty' => 5,
        'cost_price_snapshot' => 50000.00,
        'is_consignment_snapshot' => false,
        'physical_qty' => null, // UNCOUNTED
    ]);

    $action = app(SubmitStockOpnameCountAction::class);

    expect(function () use ($action, $session, $staff) {
        $action->execute($session, $staff->id);
    })->toThrow(ValidationException::class, 'Kamui Tip (TIP-01)');

    expect($session->fresh()->counting_completed_at)->toBeNull();
});

test('physical_qty = 0 is valid and recognized as counted during submission', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $owner = User::factory()->create(['role' => 'owner']);
    $product = Product::factory()->create(['name' => 'Out of stock cue', 'sku' => 'CUE-01']);

    $session = StockOpname::create([
        'session_name' => 'Audit Zero Count',
        'status' => OpnameStatus::InProgress,
        'created_by' => $owner->id,
    ]);

    $session->items()->create([
        'product_id' => $product->id,
        'system_qty' => 2,
        'cost_price_snapshot' => 1000000.00,
        'is_consignment_snapshot' => false,
        'physical_qty' => 0, // Genuinely zero physical stock
    ]);

    $action = app(SubmitStockOpnameCountAction::class);
    $submitted = $action->execute($session, $staff->id);

    expect($submitted->isCountingSubmitted())->toBeTrue()
        ->and($submitted->counting_completed_at)->not->toBeNull()
        ->and($submitted->counting_completed_by)->toBe($staff->id);
});

test('staff can submit when all items are counted and completion metadata is stored', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $owner = User::factory()->create(['role' => 'owner']);
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();

    $session = StockOpname::create([
        'session_name' => 'Full Count Sesi',
        'status' => OpnameStatus::InProgress,
        'created_by' => $owner->id,
    ]);

    $session->items()->create([
        'product_id' => $product1->id,
        'system_qty' => 10,
        'cost_price_snapshot' => 50000.00,
        'is_consignment_snapshot' => false,
        'physical_qty' => 8,
    ]);

    $session->items()->create([
        'product_id' => $product2->id,
        'system_qty' => 20,
        'cost_price_snapshot' => 30000.00,
        'is_consignment_snapshot' => false,
        'physical_qty' => 22,
    ]);

    $action = app(SubmitStockOpnameCountAction::class);
    $submitted = $action->execute($session, $staff->id);

    expect($submitted->status)->toBe(OpnameStatus::InProgress)
        ->and($submitted->isCountingSubmitted())->toBeTrue()
        ->and($submitted->counting_completed_by)->toBe($staff->id)
        ->and($submitted->countingCompletedBy->id)->toBe($staff->id);
});

test('double submission is prevented atomically', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $owner = User::factory()->create(['role' => 'owner']);
    $product = Product::factory()->create();

    $session = StockOpname::create([
        'session_name' => 'Double Submit Test',
        'status' => OpnameStatus::InProgress,
        'created_by' => $owner->id,
    ]);

    $session->items()->create([
        'product_id' => $product->id,
        'system_qty' => 10,
        'cost_price_snapshot' => 50000.00,
        'is_consignment_snapshot' => false,
        'physical_qty' => 10,
    ]);

    $action = app(SubmitStockOpnameCountAction::class);
    $action->execute($session, $staff->id);

    expect(function () use ($action, $session, $staff) {
        $action->execute($session->fresh(), $staff->id);
    })->toThrow(ValidationException::class, 'Penghitungan fisik untuk sesi ini sudah dikirimkan sebelumnya.');
});

/*
|--------------------------------------------------------------------------
| 2. Post-Submission Edit Lock Tests
|--------------------------------------------------------------------------
*/

test('staff and users cannot edit physical_qty after submission', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $owner = User::factory()->create(['role' => 'owner']);
    $product = Product::factory()->create();

    $session = StockOpname::create([
        'session_name' => 'Locked Post-Submit Sesi',
        'status' => OpnameStatus::InProgress,
        'counting_completed_at' => now(),
        'counting_completed_by' => $staff->id,
        'created_by' => $owner->id,
    ]);

    $item = $session->items()->create([
        'product_id' => $product->id,
        'system_qty' => 10,
        'cost_price_snapshot' => 50000.00,
        'is_consignment_snapshot' => false,
        'physical_qty' => 10,
    ]);

    $policy = new StockOpnameItemPolicy;

    // Policy strictly forbids updates while submitted
    expect($policy->update($staff, $item))->toBeFalse()
        ->and($policy->update($owner, $item))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| 3. Owner Review, Reopen & Approval Tests
|--------------------------------------------------------------------------
*/

test('owner cannot approve opname before counting is submitted', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $product = Product::factory()->create();

    $session = StockOpname::create([
        'session_name' => 'Premature Approval Test',
        'status' => OpnameStatus::InProgress,
        'created_by' => $owner->id,
    ]);

    $session->items()->create([
        'product_id' => $product->id,
        'system_qty' => 10,
        'cost_price_snapshot' => 50000.00,
        'is_consignment_snapshot' => false,
        'physical_qty' => 10,
    ]);

    $action = app(ApproveStockOpnameAction::class);

    expect(function () use ($action, $session, $owner) {
        $action->execute($session, $owner->id);
    })->toThrow(ValidationException::class, 'Penghitungan fisik belum diselesaikan.');
});

test('owner can approve opname after submission', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $staff = User::factory()->create(['role' => 'staff']);
    $product = Product::factory()->create(['stock' => 10]);

    $session = StockOpname::create([
        'session_name' => 'Valid Approval Test',
        'status' => OpnameStatus::InProgress,
        'created_by' => $owner->id,
    ]);

    $session->items()->create([
        'product_id' => $product->id,
        'system_qty' => 10,
        'cost_price_snapshot' => 50000.00,
        'is_consignment_snapshot' => false,
        'physical_qty' => 8,
    ]);

    app(SubmitStockOpnameCountAction::class)->execute($session, $staff->id);

    $action = app(ApproveStockOpnameAction::class);
    $approved = $action->execute($session->fresh(), $owner->id);

    expect($approved->status)->toBe(OpnameStatus::Completed)
        ->and($approved->approved_by)->toBe($owner->id)
        ->and($product->fresh()->stock)->toBe(8);
});

test('owner can reopen counting, preserving physical quantities and unlocking staff edits', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $staff = User::factory()->create(['role' => 'staff']);
    $product = Product::factory()->create();

    $session = StockOpname::create([
        'session_name' => 'Reopen Test',
        'status' => OpnameStatus::InProgress,
        'counting_completed_at' => now(),
        'counting_completed_by' => $staff->id,
        'created_by' => $owner->id,
    ]);

    $item = $session->items()->create([
        'product_id' => $product->id,
        'system_qty' => 10,
        'cost_price_snapshot' => 50000.00,
        'is_consignment_snapshot' => false,
        'physical_qty' => 7,
    ]);

    $reopenAction = app(ReopenStockOpnameCountAction::class);
    $reopened = $reopenAction->execute($session);

    expect($reopened->isCountingSubmitted())->toBeFalse()
        ->and($reopened->counting_completed_at)->toBeNull()
        ->and($reopened->counting_completed_by)->toBeNull()
        ->and($item->fresh()->physical_qty)->toBe(7); // Preserved physical qty

    // Staff can edit again
    $policy = new StockOpnameItemPolicy;
    expect($policy->update($staff, $item->fresh()))->toBeTrue();
});

test('completed stock opname cannot be reopened', function () {
    $owner = User::factory()->create(['role' => 'owner']);

    $session = StockOpname::create([
        'session_name' => 'Completed Sesi',
        'status' => OpnameStatus::Completed,
        'counting_completed_at' => now(),
        'created_by' => $owner->id,
        'approved_by' => $owner->id,
    ]);

    $reopenAction = app(ReopenStockOpnameCountAction::class);

    expect(function () use ($reopenAction, $session) {
        $reopenAction->execute($session);
    })->toThrow(ValidationException::class, "Hanya sesi berstatus 'in_progress' yang dapat dibuka kembali.");
});

test('stock opname policy authorizes submitCount and reopenCount correctly', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $staff = User::factory()->create(['role' => 'staff']);

    $activeSession = StockOpname::create([
        'session_name' => 'Active Counting',
        'status' => OpnameStatus::InProgress,
        'created_by' => $owner->id,
    ]);

    $submittedSession = StockOpname::create([
        'session_name' => 'Submitted Counting',
        'status' => OpnameStatus::InProgress,
        'counting_completed_at' => now(),
        'counting_completed_by' => $staff->id,
        'created_by' => $owner->id,
    ]);

    $policy = new StockOpnamePolicy;

    // SubmitCount: Both staff and owner can submit when open
    expect($policy->submitCount($staff, $activeSession))->toBeTrue()
        ->and($policy->submitCount($owner, $activeSession))->toBeTrue()
        ->and($policy->submitCount($staff, $submittedSession))->toBeFalse();

    // ReopenCount: ONLY owner can reopen submitted sessions
    expect($policy->reopenCount($owner, $submittedSession))->toBeTrue()
        ->and($policy->reopenCount($staff, $submittedSession))->toBeFalse()
        ->and($policy->reopenCount($owner, $activeSession))->toBeFalse();
});
