<?php

namespace App\Filament\Widgets;

use App\Enums\ProductType;
use App\Enums\SettlementStatus;
use App\Enums\TransactionStatus;
use App\Models\ConsignmentSettlement;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class OwnerStatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('owner') ?? false;
    }

    protected function getStats(): array
    {
        $today = today();
        $startOfMonth = now()->startOfMonth();

        // 1. Sales Today
        $salesToday = Transaction::where('status', TransactionStatus::Completed)
            ->whereDate('created_at', $today)
            ->sum('total_amount');

        // 2. Transactions Today
        $transactionsToday = Transaction::where('status', TransactionStatus::Completed)
            ->whereDate('created_at', $today)
            ->count();

        // 3. Items Sold Today
        $itemsSoldToday = TransactionItem::whereHas('transaction', fn ($q) => $q->where('status', TransactionStatus::Completed)->whereDate('created_at', $today))
            ->sum('quantity');

        // 4. Gross Sales This Month
        $grossSalesMonth = Transaction::where('status', TransactionStatus::Completed)
            ->where('created_at', '>=', $startOfMonth)
            ->sum('total_amount');

        // 5. Owned Inventory Cost Valuation (Regular products only)
        $ownedInventoryCost = Product::where('type', ProductType::Regular)
            ->selectRaw('SUM(stock * cost_price) as total')
            ->value('total') ?? 0;

        // 6. Consignment Inventory Valuation (Supplier-owned)
        $consignmentInventoryValuation = Product::where('type', ProductType::Consignment)
            ->selectRaw('SUM(stock * cost_price) as total')
            ->value('total') ?? 0;

        // 7. Pending Consignment Settlement
        $pendingSettlement = ConsignmentSettlement::where('status', SettlementStatus::Unpaid)
            ->sum('amount');

        // 8. Low Stock Count (Threshold <= 5)
        $lowStockCount = Product::where('stock', '<=', 5)->count();

        return [
            Stat::make('Sales Today', 'Rp '.number_format((float) $salesToday, 2, ',', '.'))
                ->description("{$transactionsToday} transaction(s) | {$itemsSoldToday} item(s) sold")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Gross Sales This Month', 'Rp '.number_format((float) $grossSalesMonth, 2, ',', '.'))
                ->description(now()->format('F Y'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),

            Stat::make('Owned Inventory Cost', 'Rp '.number_format((float) $ownedInventoryCost, 2, ',', '.'))
                ->description('Estimated working capital (Regular)')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('info'),

            Stat::make('Consignment Stock Value', 'Rp '.number_format((float) $consignmentInventoryValuation, 2, ',', '.'))
                ->description('Supplier-owned inventory in custody')
                ->descriptionIcon('heroicon-m-truck')
                ->color('gray'),

            Stat::make('Pending Consignment Payable', 'Rp '.number_format((float) $pendingSettlement, 2, ',', '.'))
                ->description('Unpaid supplier settlement balance')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($pendingSettlement > 0 ? 'warning' : 'success'),

            Stat::make('Low Stock Products', (string) $lowStockCount)
                ->description('Stock level <= 5 units')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'danger' : 'success'),
        ];
    }
}
