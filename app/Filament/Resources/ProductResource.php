<?php

namespace App\Filament\Resources;

use App\Enums\ProductType;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Products';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Identitas Produk')
                            ->description('Informasi utama identitas dan klasifikasi kepemilikan barang.')
                            ->schema([
                                Forms\Components\TextInput::make('sku')
                                    ->label('SKU (Kode Unik Produk)')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->placeholder('Contoh: REG-CHALK-01'),

                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Produk')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Nama lengkap produk barang...'),

                                Forms\Components\Select::make('type')
                                    ->label('Tipe Kepemilikan Produk')
                                    ->options(ProductType::class)
                                    ->default(ProductType::Regular->value)
                                    ->required()
                                    ->live(),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make('Supplier Konsinyasi')
                            ->description('Pilih supplier pemilik barang konsinyasi.')
                            ->schema([
                                Forms\Components\Select::make('supplier_id')
                                    ->label('Supplier Pemilik')
                                    ->relationship('supplier', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(fn (Get $get): bool => $get('type') === ProductType::Consignment->value || $get('type') === ProductType::Consignment),
                            ])
                            ->visible(fn (Get $get): bool => $get('type') === ProductType::Consignment->value || $get('type') === ProductType::Consignment),

                        Forms\Components\Section::make('Struktur Harga')
                            ->description('Penetapan harga beli modal dan harga jual eceran kasir.')
                            ->schema([
                                Forms\Components\TextInput::make('cost_price')
                                    ->label('Harga Beli / Pokok (Modal)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->minValue(0)
                                    ->required()
                                    ->helperText('Untuk produk konsinyasi, nilai ini adalah nominal yang wajib dibayarkan ke supplier saat barang terjual.'),

                                Forms\Components\TextInput::make('selling_price')
                                    ->label('Harga Jual Kasir')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->minValue(0)
                                    ->required(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Status Inventori')
                            ->schema([
                                Forms\Components\TextInput::make('stock')
                                    ->label('Stok Tersedia')
                                    ->numeric()
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Stok bertambah melalui Inbound Stock dan berkurang otomatis melalui POS Checkout.'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe')
                    ->badge(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->placeholder('Milik Toko (Regular)')
                    ->sortable(),

                Tables\Columns\TextColumn::make('cost_price')
                    ->label('Harga Pokok')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('selling_price')
                    ->label('Harga Jual')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock')
                    ->label('Stok')
                    ->numeric()
                    ->sortable()
                    ->color(fn (Product $record): string => $record->stock <= 0 ? 'danger' : ($record->stock <= 5 ? 'warning' : 'success'))
                    ->weight('bold'),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(ProductType::class),

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->emptyStateHeading('Belum Ada Produk')
            ->emptyStateDescription('Mulai daftarkan master data produk barang untuk inventori dan kasir.')
            ->emptyStateIcon('heroicon-o-cube');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
