<?php

use App\Enums\PaymentMethod;
use App\Enums\ProductType;
use App\Enums\SalesChannel;
use App\Enums\StockMovementType;
use App\Enums\TransactionStatus;
use App\Filament\Widgets\LowStockAlertWidget;
use App\Filament\Widgets\OwnerStatsOverviewWidget;
use App\Filament\Widgets\RecentStockMovementsWidget;
use App\Filament\Widgets\SalesByChannelChartWidget;
use App\Filament\Widgets\SalesByPaymentMethodChartWidget;
use App\Filament\Widgets\SalesTrendChartWidget;
use App\Filament\Widgets\TopSellingProductsWidget;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('sales by payment method chart widget can mount and load data with completed transactions', function () {
    $owner = User::factory()->create(['role' => 'owner']);

    Transaction::factory()->create([
        'status' => TransactionStatus::Completed,
        'payment_method' => PaymentMethod::Cash,
        'total_amount' => 150000,
        'created_at' => now(),
    ]);

    Transaction::factory()->create([
        'status' => TransactionStatus::Completed,
        'payment_method' => PaymentMethod::Qris,
        'total_amount' => 250000,
        'created_at' => now(),
    ]);

    $this->actingAs($owner);

    Livewire::test(SalesByPaymentMethodChartWidget::class)
        ->assertSuccessful();
});

test('sales by channel chart widget can mount and load data with completed transactions', function () {
    $owner = User::factory()->create(['role' => 'owner']);

    Transaction::factory()->create([
        'status' => TransactionStatus::Completed,
        'channel' => SalesChannel::Offline,
        'total_amount' => 150000,
        'created_at' => now(),
    ]);

    Transaction::factory()->create([
        'status' => TransactionStatus::Completed,
        'channel' => SalesChannel::Shopee,
        'total_amount' => 350000,
        'created_at' => now(),
    ]);

    $this->actingAs($owner);

    Livewire::test(SalesByChannelChartWidget::class)
        ->assertSuccessful();
});

test('all dashboard widgets mount successfully without exceptions', function () {
    $owner = User::factory()->create(['role' => 'owner']);

    $product = Product::factory()->create([
        'name' => 'Widget Chalk',
        'sku' => 'WID-01',
        'type' => ProductType::Regular,
        'stock' => 2, // triggers low stock
        'cost_price' => 10000,
        'selling_price' => 20000,
    ]);

    $transaction = Transaction::factory()->create([
        'status' => TransactionStatus::Completed,
        'channel' => SalesChannel::Offline,
        'payment_method' => PaymentMethod::Cash,
        'total_amount' => 20000,
        'created_at' => now(),
    ]);

    $transaction->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => 20000,
        'cost_price' => 10000,
        'is_consignment' => false,
    ]);

    StockMovement::create([
        'product_id' => $product->id,
        'quantity' => 1,
        'type' => StockMovementType::Out,
        'reference_note' => 'Sale',
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner);

    Livewire::test(OwnerStatsOverviewWidget::class)->assertSuccessful();
    Livewire::test(SalesTrendChartWidget::class)->assertSuccessful();
    Livewire::test(SalesByChannelChartWidget::class)->assertSuccessful();
    Livewire::test(SalesByPaymentMethodChartWidget::class)->assertSuccessful();
    Livewire::test(TopSellingProductsWidget::class)->assertSuccessful();
    Livewire::test(LowStockAlertWidget::class)->assertSuccessful();
    Livewire::test(RecentStockMovementsWidget::class)->assertSuccessful();
});
