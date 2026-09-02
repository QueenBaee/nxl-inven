<?php

namespace App\Filament\Resources;

use App\Enums\StockMovementType;
use App\Filament\Resources\InboundResource\Pages;
use App\Models\StockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class InboundResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationLabel = 'Inbound Stock';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $modelLabel = 'Inbound Stock Movement';

    protected static ?string $pluralModelLabel = 'Inbound Stock Movements';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Inbound Details')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),

                        Forms\Components\Textarea::make('reference_note')
                            ->label('Reference Note')
                            ->placeholder('e.g. Restock Master Chalk or Titipan Ko Hendra')
                            ->rows(3)
                            ->nullable()
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('type')
                            ->default(StockMovementType::In->value),

                        Forms\Components\Hidden::make('created_by')
                            ->default(fn (): ?int => Auth::id()),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', StockMovementType::In))
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('reference_note')
                    ->label('Reference Note')
                    ->placeholder('-')
                    ->limit(50),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Recorded By')
                    ->placeholder('System'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->emptyStateHeading('Belum Ada Penerimaan Barang')
            ->emptyStateDescription('Catat penerimaan stok masuk (restock / titipan konsinyasi) di sini.')
            ->emptyStateIcon('heroicon-o-arrow-down-tray');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInbounds::route('/'),
            'create' => Pages\CreateInbound::route('/create'),
        ];
    }
}
