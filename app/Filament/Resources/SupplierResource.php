<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Consignment';

    protected static ?string $navigationLabel = 'Suppliers';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Supplier')
                    ->description('Data profil supplier dan kontak penanggung jawab.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Supplier / Vendor')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: Fury Cue Official Supplier'),

                        Forms\Components\TextInput::make('contact_person')
                            ->label('Nama Kontak (PIC)')
                            ->maxLength(255)
                            ->placeholder('Nama perwakilan supplier...'),

                        Forms\Components\TextInput::make('phone')
                            ->label('Nomor Telepon / WhatsApp')
                            ->tel()
                            ->maxLength(255)
                            ->placeholder('Contoh: 081234567890'),

                        Forms\Components\Textarea::make('address')
                            ->label('Alamat Kantor / Gudang')
                            ->rows(3)
                            ->placeholder('Alamat lengkap...')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Supplier')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('contact_person')
                    ->label('PIC / Kontak')
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telepon')
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('SKU Konsinyasi')
                    ->counts('products')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateHeading('Belum Ada Supplier')
            ->emptyStateDescription('Daftarkan supplier rekanan untuk mengelola produk barang konsinyasi.')
            ->emptyStateIcon('heroicon-o-truck');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
