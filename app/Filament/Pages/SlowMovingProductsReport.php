<?php

namespace App\Filament\Pages;

use App\Enums\TransactionStatus;
use App\Models\Product;
use App\Models\TransactionItem;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SlowMovingProductsReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Reports & Analytics';

    protected static ?string $navigationLabel = 'Slow Moving Products';

    protected static ?string $title = 'Laporan Barang Lambat / Tidak Bergerak (Dead Stock)';

    protected static string $view = 'filament.pages.slow-moving-products-report';

    protected static ?int $navigationSort = 4;

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
                    ->where('stock', '>', 0)
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
                    ->label('Tipe')
                    ->badge(),

                Tables\Columns\TextColumn::make('stock')
                    ->label('Sisa Stok')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cost_price')
                    ->label('Harga Modal')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('capital_tied_up')
                    ->label('Modal Mengendap')
                    ->state(fn (Product $record): string => bcmul((string) $record->cost_price, (string) $record->stock, 2))
                    ->money('IDR')
                    ->sortable(false)
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('last_sold_date')
                    ->label('Penjualan Terakhir')
                    ->state(function (Product $record): string {
                        $lastSaleDate = TransactionItem::where('product_id', $record->id)
                            ->whereHas('transaction', fn ($q) => $q->where('status', TransactionStatus::Completed))
                            ->latest('created_at')
                            ->value('created_at');

                        return $lastSaleDate ? Carbon::parse($lastSaleDate)->format('d M Y') : 'Belum Pernah Terjual';
                    }),
            ])
            ->defaultSort('stock', 'desc')
            ->filters([
                Tables\Filters\Filter::make('inactivity_period')
                    ->form([
                        Forms\Components\Select::make('period')
                            ->label('Periode Tidak Bergerak')
                            ->options([
                                '30' => 'Tidak ada penjualan 30 hari terakhir',
                                '60' => 'Tidak ada penjualan 60 hari terakhir',
                                '90' => 'Tidak ada penjualan 90 hari terakhir',
                                'never' => 'Belum pernah terjual sama sekali',
                            ])
                            ->default('30'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $period = $data['period'] ?? '30';

                        if ($period === 'never') {
                            return $query->whereDoesntHave('transactionItems', function ($q) {
                                $q->whereHas('transaction', fn ($t) => $t->where('status', TransactionStatus::Completed));
                            });
                        }

                        $cutoffDate = now()->subDays((int) $period)->startOfDay();

                        return $query->whereDoesntHave('transactionItems', function ($q) use ($cutoffDate) {
                            $q->whereHas('transaction', fn ($t) => $t->where('status', TransactionStatus::Completed))
                                ->where('created_at', '>=', $cutoffDate);
                        });
                    }),
            ])
            ->emptyStateHeading('Semua Produk Bergerak Aktif')
            ->emptyStateDescription('Tidak ditemukan produk dengan pergerakan lambat pada filter periode ini.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
