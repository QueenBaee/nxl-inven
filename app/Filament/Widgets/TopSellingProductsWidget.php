<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionStatus;
use App\Models\Product;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TopSellingProductsWidget extends BaseWidget
{
    protected static ?string $heading = 'Produk Terlaris (30 Hari Terakhir)';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 2,
    ];

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('owner') ?? false;
    }

    public function table(Table $table): Table
    {
        $startDate = now()->subDays(30)->startOfDay();

        return $table
            ->query(
                Product::query()
                    ->join('transaction_items', 'products.id', '=', 'transaction_items.product_id')
                    ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                    ->where('transactions.status', TransactionStatus::Completed->value)
                    ->where('transactions.created_at', '>=', $startDate)
                    ->select([
                        'products.id',
                        'products.name',
                        'products.sku',
                        'products.type',
                        'products.stock',
                        DB::raw('SUM(transaction_items.quantity) as total_sold'),
                        DB::raw('SUM(transaction_items.quantity * transaction_items.price) as gross_revenue'),
                    ])
                    ->groupBy('products.id', 'products.name', 'products.sku', 'products.type', 'products.stock')
                    ->orderByDesc('total_sold')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable(),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe')
                    ->badge(),

                Tables\Columns\TextColumn::make('stock')
                    ->label('Sisa Stok')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_sold')
                    ->label('Terjual')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('gross_revenue')
                    ->label('Total Omset')
                    ->money('IDR')
                    ->sortable(),
            ])
            ->emptyStateHeading('Belum Ada Penjualan')
            ->emptyStateDescription('Belum ada riwayat penjualan produk dalam 30 hari terakhir.')
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->paginated(false);
    }
}
