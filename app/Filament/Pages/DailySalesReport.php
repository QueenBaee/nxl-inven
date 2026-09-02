<?php

namespace App\Filament\Pages;

use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DailySalesReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Reports & Analytics';

    protected static ?string $navigationLabel = 'Daily Sales Report';

    protected static ?string $title = 'Laporan Penjualan & Omset Harian';

    protected static string $view = 'filament.pages.daily-sales-report';

    protected static ?int $navigationSort = 1;

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
                Transaction::query()
                    ->with(['items.product', 'creator'])
                    ->where('status', TransactionStatus::Completed)
            )
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal & Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('channel')
                    ->label('Channel')
                    ->badge(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Metode Bayar')
                    ->badge(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Total Item')
                    ->counts('items')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Gross Sales (Omset)')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Kasir')
                    ->placeholder('Sistem'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
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

                Tables\Filters\SelectFilter::make('channel')
                    ->options(SalesChannel::class),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->options(PaymentMethod::class),
            ])
            ->emptyStateHeading('Belum Ada Transaksi')
            ->emptyStateDescription('Belum ada transaksi penjualan selesai pada rentang tanggal yang dipilih.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }
}
