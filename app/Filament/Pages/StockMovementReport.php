<?php

namespace App\Filament\Pages;

use App\Enums\StockMovementType;
use App\Models\StockMovement;
use App\Models\User;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StockMovementReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Reports & Analytics';

    protected static ?string $navigationLabel = 'Stock Movement Report';

    protected static ?string $title = 'Laporan Audit Mutasi & Buku Stok';

    protected static string $view = 'filament.pages.stock-movement-report';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('owner') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockMovement::query()
                    ->with(['product', 'creator', 'transaction', 'stockOpnameItem.stockOpname'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe Mutasi')
                    ->badge(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('source')
                    ->label('Sumber Mutasi')
                    ->state(function (StockMovement $record): string {
                        if ($record->transaction) {
                            return "Penjualan: {$record->transaction->invoice_number}";
                        }
                        if ($record->stockOpnameItem?->stockOpname) {
                            return "Stock Opname: {$record->stockOpnameItem->stockOpname->session_name}";
                        }

                        return $record->reference_note ?: 'Inbound / Restock Manual';
                    })
                    ->searchable(false),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Petugas')
                    ->placeholder('Sistem'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipe')
                    ->options(StockMovementType::class),

                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Produk')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from_date')->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('to_date')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from_date'], fn (Builder $q) => $q->whereDate('created_at', '>=', $data['from_date']))
                            ->when($data['to_date'], fn (Builder $q) => $q->whereDate('created_at', '<=', $data['to_date']));
                    }),
            ])
            ->emptyStateHeading('Belum Ada Mutasi Stok')
            ->emptyStateDescription('Belum ada data pergerakan barang pada filter yang dipilih.')
            ->emptyStateIcon('heroicon-o-arrows-right-left');
    }
}
