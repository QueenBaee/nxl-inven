<?php

use App\Actions\StockOpname\StartStockOpnameAction;
use App\Enums\OpnameStatus;
use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Livewire\PosCart;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| 1. Barcode Scanning Workflow Tests
|--------------------------------------------------------------------------
*/

test('exact barcode scan adds product to cart and clears input', function () {
    $product = Product::factory()->create([
        'sku' => '8991234567890',
        'name' => 'Taom Pyro Chalk',
        'stock' => 10,
        'selling_price' => 350000.00,
    ]);

    Livewire::test(PosCart::class)
        ->set('barcode', '8991234567890')
        ->call('scanBarcode')
        ->assertSet('barcode', '')
        ->assertDispatched('play-scan-success')
        ->assertDispatched('focus-barcode-input')
        ->assertSee('Taom Pyro Chalk')
        ->assertSee('1 item(s) terpilih');
});

test('scanning unknown barcode displays safe error message and plays error sound', function () {
    Livewire::test(PosCart::class)
        ->set('barcode', 'INVALID-BARCODE-999')
        ->call('scanBarcode')
        ->assertSet('barcode', '')
        ->assertDispatched('play-scan-error')
        ->assertSee('Barcode tidak ditemukan.');
});

test('scanning product with zero stock displays out of stock alert and rejects addition', function () {
    $product = Product::factory()->create([
        'sku' => 'EMPTY-SKU-01',
        'stock' => 0,
    ]);

    Livewire::test(PosCart::class)
        ->set('barcode', 'EMPTY-SKU-01')
        ->call('scanBarcode')
        ->assertDispatched('play-scan-error')
        ->assertSee('Stok produk habis.')
        ->assertSee('0 item(s) terpilih');
});

test('scanning product frozen by active stock opname displays freeze alert and rejects addition', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $product = Product::factory()->create([
        'sku' => 'FROZEN-SKU-01',
        'stock' => 15,
    ]);

    $opname = StockOpname::create([
        'session_name' => 'Active Store Audit',
        'status' => OpnameStatus::Draft,
        'created_by' => $owner->id,
    ]);

    app(StartStockOpnameAction::class)->execute($opname);

    Livewire::test(PosCart::class)
        ->set('barcode', 'FROZEN-SKU-01')
        ->call('scanBarcode')
        ->assertDispatched('play-scan-error')
        ->assertSee('Produk sedang dikunci karena Stock Opname aktif.')
        ->assertSee('0 item(s) terpilih');
});

test('rapid repeated scanning increments quantity correctly up to available stock', function () {
    $product = Product::factory()->create([
        'sku' => 'REPEAT-SKU-01',
        'stock' => 3,
        'selling_price' => 50000.00,
    ]);

    $test = Livewire::test(PosCart::class);

    // Scan 1
    $test->set('barcode', 'REPEAT-SKU-01')->call('scanBarcode');
    // Scan 2
    $test->set('barcode', 'REPEAT-SKU-01')->call('scanBarcode');
    // Scan 3 (reaches max available stock 3)
    $test->set('barcode', 'REPEAT-SKU-01')->call('scanBarcode');

    $test->assertSee('3 item(s) terpilih')
        ->assertSee('Rp 150.000,00');

    // Scan 4 (should exceed stock and reject)
    $test->set('barcode', 'REPEAT-SKU-01')
        ->call('scanBarcode')
        ->assertDispatched('play-scan-error')
        ->assertSee('Stok produk tidak mencukupi untuk penambahan.')
        ->assertSee('3 item(s) terpilih');
});

/*
|--------------------------------------------------------------------------
| 2. Receipt Generation, Printing & Immutability Tests
|--------------------------------------------------------------------------
*/

test('receipt is accessible to authenticated users and reflects immutable transaction totals', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Taom Pyro', 'sku' => 'TAOM-01', 'stock' => 10]);

    $transaction = Transaction::create([
        'invoice_number' => 'INV-20260901-9999',
        'total_amount' => 350000.00,
        'payment_method' => PaymentMethod::Qris,
        'channel' => SalesChannel::Offline,
        'created_by' => $user->id,
    ]);

    $transaction->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => 350000.00,
        'cost_price' => 250000.00,
        'is_consignment' => false,
    ]);

    $response = $this->actingAs($user)->get(route('receipt.show', $transaction));

    $response->assertOk()
        ->assertSee('INV-20260901-9999')
        ->assertSee('Taom Pyro')
        ->assertSee('TAOM-01')
        ->assertSee('350.000');
});

test('unauthenticated users are redirected from receipt endpoint', function () {
    $transaction = Transaction::factory()->create();

    $response = $this->get(route('receipt.show', $transaction));
    $response->assertRedirect(route('login'));
});

test('viewing or reprinting receipt 10 times never alters stock or creates new stock movements', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 10]);
    $transaction = Transaction::factory()->create(['total_amount' => 100000.00]);

    $transaction->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => 100000.00,
        'cost_price' => 50000.00,
        'is_consignment' => false,
    ]);

    $initialMovementsCount = StockMovement::count();
    $initialTxCount = Transaction::count();
    $initialItemCount = $transaction->items()->count();

    // Call receipt endpoint 10 times consecutively
    for ($i = 0; $i < 10; $i++) {
        $response = $this->actingAs($user)->get(route('receipt.show', $transaction));
        $response->assertOk();
    }

    // Verify stock, movements, transactions, and line items remain strictly identical
    expect($product->fresh()->stock)->toBe(10)
        ->and(StockMovement::count())->toBe($initialMovementsCount)
        ->and(Transaction::count())->toBe($initialTxCount)
        ->and($transaction->fresh()->items()->count())->toBe($initialItemCount)
        ->and((float) $transaction->fresh()->total_amount)->toBe(100000.00);
});

/*
|--------------------------------------------------------------------------
| 3. Deployment Health Check Endpoint Tests
|--------------------------------------------------------------------------
*/

test('production health check endpoint responds with HTTP 200 OK without leaking sensitive details', function () {
    $response = $this->get('/up');
    $response->assertOk();

    // Health endpoint must not leak DB credentials or app keys
    $response->assertDontSee('DB_PASSWORD')
        ->assertDontSee('APP_KEY')
        ->assertDontSee('APP_DEBUG');
});
