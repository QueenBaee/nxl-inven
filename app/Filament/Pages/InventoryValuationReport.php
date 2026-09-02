<?php

namespace App\Filament\Pages;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InventoryValuationReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Reports & Analytics';

    protected static ?string $navigationLabel = 'Inventory Valuation';

    protected static ?string $title = 'Laporan Valuasi Modal Inventori';

    protected static string $view = 'filament.pages.inventory-valuation-report';

    protected static ?int $navigationSort = 2;

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
                Product::query()
                    ->with('supplier')
            )
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe Kepemilikan')
                    ->badge(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier / Pemilik')
                    ->placeholder('Milik Toko (Regular)')
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock')
                    ->label('Stok Fisik')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cost_price')
                    ->label('Harga Pokok (Modal)')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_cost_valuation')
                    ->label('Total Valuasi Modal')
                    ->state(fn (Product $record): string => bcmul((string) $record->cost_price, (string) $record->stock, 2))
                    ->money('IDR')
                    ->sortable(false)
                    ->weight('bold'),
            ])
            ->defaultSort('stock', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Kepemilikan')
                    ->options(ProductType::class),

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->emptyStateHeading('Belum Ada Data Inventori')
            ->emptyStateDescription('Data valuasi modal akan otomatis muncul berdasarkan master produk terdaftar.')
            ->emptyStateIcon('heroicon-o-calculator');
    }
}
