<?php

namespace App\Filament\Widgets;

use App\Models\StockMovement;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentStockMovementsWidget extends BaseWidget
{
    protected static ?string $heading = 'Mutasi Stok Terkini (Audit Trail)';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockMovement::query()
                    ->with(['product', 'creator', 'transaction', 'stockOpnameItem.stockOpname'])
                    ->latest()
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Nama Produk')
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe Mutasi')
                    ->badge(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric(),

                Tables\Columns\TextColumn::make('source')
                    ->label('Sumber Mutasi')
                    ->state(function (StockMovement $record): string {
                        if ($record->transaction) {
                            return "Penjualan ({$record->transaction->invoice_number})";
                        }
                        if ($record->stockOpnameItem?->stockOpname) {
                            return "Stock Opname ({$record->stockOpnameItem->stockOpname->session_name})";
                        }

                        return $record->reference_note ?: 'Inbound / Restock Manual';
                    }),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Petugas')
                    ->placeholder('Sistem'),
            ])
            ->emptyStateHeading('Belum Ada Mutasi Stok')
            ->emptyStateDescription('Belum ada riwayat pergerakan stok yang tercatat.')
            ->emptyStateIcon('heroicon-o-arrows-right-left')
            ->paginated(false);
    }
}
