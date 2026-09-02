<?php

namespace App\Filament\Resources\StockOpnameResource\RelationManagers;

use App\Enums\OpnameStatus;
use App\Models\StockOpnameItem;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Daftar Item Audit Fisik (Blind Count)';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('physical_qty')
                    ->label('Kuantitas Hitung Fisik')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->disabled(fn () => $this->getOwnerRecord()->status !== OpnameStatus::InProgress || $this->getOwnerRecord()->isCountingSubmitted()),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isOwner = $user?->hasRole('owner') ?? false;

        $columns = [
            Tables\Columns\TextColumn::make('product.sku')
                ->label('SKU')
                ->fontFamily('mono')
                ->weight('bold')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('product.name')
                ->label('Nama Produk')
                ->searchable()
                ->sortable()
                ->weight('bold'),

            Tables\Columns\TextColumn::make('count_status')
                ->label('Status Hitung')
                ->state(fn (StockOpnameItem $record): string => $record->physical_qty !== null ? 'Sudah Dihitung' : 'Belum Dihitung')
                ->badge()
                ->color(fn (StockOpnameItem $record): string => $record->physical_qty !== null ? 'success' : 'gray'),

            Tables\Columns\TextInputColumn::make('physical_qty')
                ->label('Qty Fisik')
                ->disabled(fn () => $this->getOwnerRecord()->status !== OpnameStatus::InProgress || $this->getOwnerRecord()->isCountingSubmitted())
                ->rules(['required', 'numeric', 'min:0']),
        ];

        // Blind Count Protection: Only Owner/Review mode loads and displays system counts, variances, and loss/liability calculations
        if ($isOwner) {
            $columns[] = Tables\Columns\TextColumn::make('system_qty')
                ->label('Qty Sistem')
                ->sortable();

            $columns[] = Tables\Columns\TextColumn::make('variance')
                ->label('Selisih (Variance)')
                ->state(fn (StockOpnameItem $record): ?string => $record->variance !== null ? ($record->variance > 0 ? "+{$record->variance}" : (string) $record->variance) : 'Belum Dihitung')
                ->color(fn (StockOpnameItem $record): string => match (true) {
                    $record->variance === null => 'gray',
                    $record->variance < 0 => 'danger',
                    $record->variance > 0 => 'info',
                    default => 'success',
                })
                ->sortable(false);

            $columns[] = Tables\Columns\TextColumn::make('financial_impact')
                ->label('Dampak Finansial')
                ->state(function (StockOpnameItem $record): string {
                    if ($record->variance === null) {
                        return 'Belum Dihitung';
                    }
                    if ($record->variance >= 0) {
                        return 'Rp 0 (Aman / Surplus)';
                    }

                    $formatted = 'Rp '.number_format($record->loss_value, 2, ',', '.');

                    return $record->varianceType() === 'supplier_liability'
                        ? "Hutang Supplier: {$formatted}"
                        : "Kerugian Toko: {$formatted}";
                })
                ->color(function (StockOpnameItem $record): string {
                    if ($record->variance === null || $record->variance >= 0) {
                        return 'gray';
                    }

                    return $record->varianceType() === 'supplier_liability' ? 'warning' : 'danger';
                });
        }

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($isOwner): Builder {
                if (! $isOwner) {
                    // Blind count query scope: do not select system count or cost price for staff
                    return $query->select(['id', 'stock_opname_id', 'product_id', 'physical_qty', 'created_at']);
                }

                return $query;
            })
            ->columns($columns)
            ->filters([
                Tables\Filters\SelectFilter::make('counted_status')
                    ->label('Status Hitung')
                    ->options([
                        'uncounted' => 'Belum Dihitung',
                        'counted' => 'Sudah Dihitung',
                    ])
                    ->query(function (Builder $query, array $data) {
                        return match ($data['value'] ?? null) {
                            'uncounted' => $query->whereNull('physical_qty'),
                            'counted' => $query->whereNotNull('physical_qty'),
                            default => $query,
                        };
                    }),
            ])
            ->emptyStateHeading('Tidak Ada Item Ditemukan')
            ->emptyStateDescription('Tidak ada produk yang sesuai dengan filter atau kata kunci pencarian.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }
}
