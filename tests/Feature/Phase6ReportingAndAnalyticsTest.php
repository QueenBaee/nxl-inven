<?php

use App\Enums\PaymentMethod;
use App\Enums\ProductType;
use App\Enums\SalesChannel;
use App\Enums\SettlementStatus;
use App\Enums\TransactionStatus;
use App\Filament\Pages\ConsignmentExposureReport;
use App\Filament\Pages\DailySalesReport;
use App\Filament\Pages\InventoryValuationReport;
use App\Filament\Pages\SlowMovingProductsReport;
use App\Filament\Pages\StockMovementReport;
use App\Filament\Widgets\OwnerStatsOverviewWidget;
use App\Filament\Widgets\SalesByChannelChartWidget;
use App\Filament\Widgets\SalesByPaymentMethodChartWidget;
use App\Filament\Widgets\SalesTrendChartWidget;
use App\Filament\Widgets\TopSellingProductsWidget;
use App\Models\ConsignmentSettlement;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| 1. Dashboard KPI Calculations & Widgets Tests
|--------------------------------------------------------------------------
*/

test('dashboard kpi calculations accurately reflect daily sales, month gross, and inventory cost', function () {
    Carbon::setTestNow('2026-09-01 14:00:00');

    $owner = User::factory()->create(['role' => 'owner']);
    Auth::login($owner);

    $supplier = Supplier::create([
        'name' => 'Test Supplier',
        'contact_person' => 'Mr. Li',
        'phone' => '0812345678',
    ]);

    // 1. Regular product with stock 10, cost 20,000 -> Owned valuation = 200,000
    $regProduct = Product::create([
        'sku' => 'VAL-REG-01',
        'name' => 'Regular Item',
        'stock' => 10,
        'cost_price' => 20000.00,
        'selling_price' => 30000.00,
        'type' => ProductType::Regular,
    ]);

    // 2. Consignment product with stock 5, cost 100,000 -> Consignment valuation = 500,000
    $conProduct = Product::create([
        'sku' => 'VAL-CON-01',
        'name' => 'Consignment Item',
        'stock' => 5,
        'cost_price' => 100000.00,
        'selling_price' => 150000.00,
        'type' => ProductType::Consignment,
        'supplier_id' => $supplier->id,
    ]);

    // 3. Transactions today (2 sales)
    $t1 = Transaction::create([
        'invoice_number' => 'INV-20260901-0001',
        'total_amount' => 60000.00,
        'payment_method' => PaymentMethod::Cash,
        'channel' => SalesChannel::Offline,
        'status' => TransactionStatus::Completed,
    ]);

    $t1->items()->create([
        'product_id' => $regProduct->id,
        'quantity' => 2,
        'price' => 30000.00,
        'cost_price' => 20000.00,
        'is_consignment' => false,
    ]);

    $t2 = Transaction::create([
        'invoice_number' => 'INV-20260901-0002',
        'total_amount' => 150000.00,
        'payment_method' => PaymentMethod::Qris,
        'channel' => SalesChannel::Shopee,
        'status' => TransactionStatus::Completed,
    ]);

    // TransactionItemObserver automatically creates ConsignmentSettlement on creation
    $t2->items()->create([
        'product_id' => $conProduct->id,
        'quantity' => 1,
        'price' => 150000.00,
        'cost_price' => 100000.00,
        'is_consignment' => true,
    ]);

    // Yesterday transaction (should not count for today)
    $tYesterday = Transaction::create([
        'invoice_number' => 'INV-20260831-0001',
        'total_amount' => 90000.00,
        'payment_method' => PaymentMethod::Cash,
        'channel' => SalesChannel::Offline,
        'status' => TransactionStatus::Completed,
    ]);
    $tYesterday->timestamps = false;
    $tYesterday->created_at = Carbon::parse('2026-08-31 10:00:00');
    $tYesterday->save();

    // Test Today's Gross Sales: 60,000 + 150,000 = 210,000
    $salesToday = Transaction::where('status', TransactionStatus::Completed)
        ->whereDate('created_at', today())
        ->sum('total_amount');
    expect((float) $salesToday)->toBe(210000.00);

    // Test Today's Transaction Count: 2
    $txTodayCount = Transaction::where('status', TransactionStatus::Completed)
        ->whereDate('created_at', today())
        ->count();
    expect($txTodayCount)->toBe(2);

    // Test Today's Items Sold Quantity: 2 + 1 = 3
    $itemsSoldToday = TransactionItem::whereHas('transaction', fn ($q) => $q->where('status', TransactionStatus::Completed)->whereDate('created_at', today()))
        ->sum('quantity');
    expect($itemsSoldToday)->toBe(3);

    // Test Owned Inventory Cost Valuation: 10 * 20,000 = 200,000 (Excludes consignment!)
    $ownedValuation = Product::where('type', ProductType::Regular)
        ->selectRaw('SUM(stock * cost_price) as total')
        ->value('total');
    expect((float) $ownedValuation)->toBe(200000.00);

    // Test Consignment Inventory Valuation: 5 * 100,000 = 500,000
    $conValuation = Product::where('type', ProductType::Consignment)
        ->selectRaw('SUM(stock * cost_price) as total')
        ->value('total');
    expect((float) $conValuation)->toBe(500000.00);

    // Test Pending Settlement Payable: 100,000
    $pendingPayable = ConsignmentSettlement::where('status', SettlementStatus::Unpaid)->sum('amount');
    expect((float) $pendingPayable)->toBe(100000.00);
});

/*
|--------------------------------------------------------------------------
| 2. Top-Selling & Slow-Moving Products Logic Tests
|--------------------------------------------------------------------------
*/

test('top selling products query ranks items correctly by quantity sold', function () {
    $p1 = Product::factory()->create(['name' => 'Fast Chalk', 'sku' => 'FC-01']);
    $p2 = Product::factory()->create(['name' => 'Slow Glove', 'sku' => 'SG-01']);

    $tx = Transaction::create([
        'invoice_number' => 'INV-20260901-0010',
        'total_amount' => 500000.00,
        'payment_method' => PaymentMethod::Cash,
        'channel' => SalesChannel::Offline,
        'status' => TransactionStatus::Completed,
        'created_at' => now(),
    ]);

    // 10 units of p1 sold
    $tx->items()->create([
        'product_id' => $p1->id,
        'quantity' => 10,
        'price' => 30000.00,
        'cost_price' => 15000.00,
        'is_consignment' => false,
    ]);

    // 2 units of p2 sold
    $tx->items()->create([
        'product_id' => $p2->id,
        'quantity' => 2,
        'price' => 100000.00,
        'cost_price' => 50000.00,
        'is_consignment' => false,
    ]);

    $topProducts = Product::query()
        ->join('transaction_items', 'products.id', '=', 'transaction_items.product_id')
        ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
        ->where('transactions.status', TransactionStatus::Completed->value)
        ->select([
            'products.id',
            'products.name',
            DB::raw('SUM(transaction_items.quantity) as total_sold'),
        ])
        ->groupBy('products.id', 'products.name')
        ->orderByDesc('total_sold')
        ->get();

    expect($topProducts->first()->id)->toBe($p1->id)
        ->and((int) $topProducts->first()->total_sold)->toBe(10)
        ->and($topProducts->last()->id)->toBe($p2->id)
        ->and((int) $topProducts->last()->total_sold)->toBe(2);
});

test('slow moving products report identifies products with zero sales in cutoff window', function () {
    $activeProduct = Product::factory()->create(['name' => 'Active Item', 'stock' => 5]);
    $deadProduct = Product::factory()->create(['name' => 'Dead Stock Item', 'stock' => 10]);

    $tx = Transaction::create([
        'invoice_number' => 'INV-20260901-0020',
        'total_amount' => 50000.00,
        'payment_method' => PaymentMethod::Cash,
        'channel' => SalesChannel::Offline,
        'status' => TransactionStatus::Completed,
        'created_at' => now()->subDays(5),
    ]);

    $tx->items()->create([
        'product_id' => $activeProduct->id,
        'quantity' => 1,
        'price' => 50000.00,
        'cost_price' => 25000.00,
        'is_consignment' => false,
    ]);

    $cutoffDate = now()->subDays(30)->startOfDay();

    // Query slow moving items (no sales in last 30 days)
    $slowItems = Product::query()
        ->where('stock', '>', 0)
        ->whereDoesntHave('transactionItems', function ($q) use ($cutoffDate) {
            $q->whereHas('transaction', fn ($t) => $t->where('status', TransactionStatus::Completed))
                ->where('created_at', '>=', $cutoffDate);
        })
        ->pluck('id');

    expect($slowItems)->toContain($deadProduct->id)
        ->and($slowItems)->not->toContain($activeProduct->id);
});

/*
|--------------------------------------------------------------------------
| 3. Role-Based Access Control on Reports & Analytics Pages
|--------------------------------------------------------------------------
*/

test('role-based authorization restricts financial reports and widgets to owner', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $staff = User::factory()->create(['role' => 'staff']);

    // Owner checks
    Auth::login($owner);
    expect(DailySalesReport::canAccess())->toBeTrue()
        ->and(InventoryValuationReport::canAccess())->toBeTrue()
        ->and(StockMovementReport::canAccess())->toBeTrue()
        ->and(SlowMovingProductsReport::canAccess())->toBeTrue()
        ->and(ConsignmentExposureReport::canAccess())->toBeTrue()
        ->and(OwnerStatsOverviewWidget::canView())->toBeTrue()
        ->and(SalesTrendChartWidget::canView())->toBeTrue()
        ->and(SalesByChannelChartWidget::canView())->toBeTrue()
        ->and(SalesByPaymentMethodChartWidget::canView())->toBeTrue()
        ->and(TopSellingProductsWidget::canView())->toBeTrue();

    // Staff checks (strictly forbidden)
    Auth::login($staff);
    expect(DailySalesReport::canAccess())->toBeFalse()
        ->and(InventoryValuationReport::canAccess())->toBeFalse()
        ->and(StockMovementReport::canAccess())->toBeFalse()
        ->and(SlowMovingProductsReport::canAccess())->toBeFalse()
        ->and(ConsignmentExposureReport::canAccess())->toBeFalse()
        ->and(OwnerStatsOverviewWidget::canView())->toBeFalse()
        ->and(SalesTrendChartWidget::canView())->toBeFalse()
        ->and(SalesByChannelChartWidget::canView())->toBeFalse()
        ->and(SalesByPaymentMethodChartWidget::canView())->toBeFalse()
        ->and(TopSellingProductsWidget::canView())->toBeFalse();
});
