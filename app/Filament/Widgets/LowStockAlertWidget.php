<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockAlertWidget extends BaseWidget
{
    protected static ?string $heading = 'Peringatan Stok Menipis (Restock)';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->with('supplier')
                    ->where('stock', '<=', 5)
                    ->orderBy('stock')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Produk')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe')
                    ->badge(),

                Tables\Columns\TextColumn::make('stock')
                    ->label('Sisa')
                    ->color('danger')
                    ->weight('bold')
                    ->sortable(),
            ])
            ->emptyStateHeading('Stok Aman')
            ->emptyStateDescription('Semua stok produk saat ini berada di atas batas minimum (5 pcs).')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5);
    }
}
