<?php

namespace App\Filament\Resources;

use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Enums\TransactionStatus;
use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers\ItemsRelationManager;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Transactions';

    protected static ?string $modelLabel = 'POS Transaction';

    protected static ?string $pluralModelLabel = 'POS Transactions';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Ringkasan Transaksi Penjualan')
                    ->description('Detail transaksi penjualan kasir (Data permanen / immutable).')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Nomor Invoice')
                            ->disabled(),

                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Transaksi')
                            ->prefix('Rp')
                            ->disabled(),

                        Forms\Components\Select::make('channel')
                            ->label('Sales Channel')
                            ->options(SalesChannel::class)
                            ->disabled(),

                        Forms\Components\Select::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->options(PaymentMethod::class)
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Status Transaksi')
                            ->options(TransactionStatus::class)
                            ->disabled(),

                        Forms\Components\TextInput::make('created_at')
                            ->label('Waktu Transaksi')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Omset')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('channel')
                    ->label('Channel')
                    ->badge(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Metode Bayar')
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Kasir')
                    ->placeholder('Sistem'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->options(SalesChannel::class),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->options(PaymentMethod::class),

                Tables\Filters\SelectFilter::make('status')
                    ->options(TransactionStatus::class),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('printReceipt')
                    ->label('Cetak Struk')
                    ->icon('heroicon-o-printer')
                    ->color('warning')
                    ->url(fn (Transaction $record): string => route('receipt.show', $record))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Belum Ada Transaksi Penjualan')
            ->emptyStateDescription('Transaksi penjualan kasir akan otomatis tercatat di sini setelah checkout di POS.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'view' => Pages\ViewTransaction::route('/{record}'),
        ];
    }
}
