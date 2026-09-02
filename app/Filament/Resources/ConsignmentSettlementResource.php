<?php

namespace App\Filament\Resources;

use App\Enums\SettlementStatus;
use App\Events\PayoutExecuted;
use App\Filament\Resources\ConsignmentSettlementResource\Pages;
use App\Models\ConsignmentSettlement;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ConsignmentSettlementResource extends Resource
{
    protected static ?string $model = ConsignmentSettlement::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Consignment';

    protected static ?string $navigationLabel = 'Settlements';

    protected static ?string $modelLabel = 'Consignment Settlement';

    protected static ?string $pluralModelLabel = 'Consignment Settlements';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('transactionItem.product.name')
                    ->label('Produk Terjual')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('transactionItem.quantity')
                    ->label('Qty')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Hutang Pokok')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('payout_reference')
                    ->label('Ref Payout')
                    ->searchable()
                    ->placeholder('-')
                    ->copyable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Tgl Bayar')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->options(SettlementStatus::class),
            ])
            ->headerActions([
                Tables\Actions\Action::make('payoutSupplier')
                    ->label('Bayar Payout Supplier')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->authorize(fn (): bool => (bool) auth()->user()?->hasRole('owner'))
                    ->form([
                        Forms\Components\Select::make('supplier_id')
                            ->label('Pilih Supplier')
                            ->options(fn () => Supplier::whereHas('consignmentSettlements', fn ($query) => $query->where('status', SettlementStatus::Unpaid))->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),

                        Forms\Components\Placeholder::make('unpaid_summary')
                            ->label('Total Tagihan Belum Dibayar')
                            ->content(function (Get $get): string {
                                $supplierId = $get('supplier_id');
                                if (! $supplierId) {
                                    return 'Pilih supplier untuk menghitung total tagihan outstanding.';
                                }

                                $unpaidQuery = ConsignmentSettlement::where('supplier_id', $supplierId)
                                    ->where('status', SettlementStatus::Unpaid);

                                $count = $unpaidQuery->count();
                                $total = (float) $unpaidQuery->sum('amount');

                                return sprintf('%d item belum lunas | Total: Rp %s', $count, number_format($total, 2, ',', '.'));
                            }),
                    ])
                    ->modalHeading('Konfirmasi Payout Supplier')
                    ->modalDescription('Proses ini akan mengubah status seluruh tagihan konsinyasi yang belum dibayar untuk supplier ini menjadi PAID dan menerbitkan kode referensi pembayaran.')
                    ->action(function (array $data): void {
                        $supplierId = (int) $data['supplier_id'];

                        DB::transaction(function () use ($supplierId): void {
                            // Lock and re-validate all unpaid settlements for this supplier
                            $unpaidSettlements = ConsignmentSettlement::where('supplier_id', $supplierId)
                                ->where('status', SettlementStatus::Unpaid)
                                ->lockForUpdate()
                                ->get();

                            if ($unpaidSettlements->isEmpty()) {
                                Notification::make()
                                    ->title('Tidak Ada Tagihan')
                                    ->body('Tidak ada tagihan outstanding yang belum dibayar untuk supplier yang dipilih.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            // Generate sequential daily payout reference: PAYOUT-{supplier_id}-{Ymd}-{seq}
                            $today = now()->format('Ymd');
                            $prefix = "PAYOUT-{$supplierId}-{$today}-";

                            $latestPayout = ConsignmentSettlement::where('payout_reference', 'like', "{$prefix}%")
                                ->lockForUpdate()
                                ->orderByDesc('id')
                                ->first();

                            $nextSeq = 1;
                            if ($latestPayout && preg_match('/-(\d{4})$/', $latestPayout->payout_reference, $matches)) {
                                $nextSeq = ((int) $matches[1]) + 1;
                            }

                            $payoutReference = $prefix.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
                            $totalAmount = (float) $unpaidSettlements->sum('amount');
                            $settlementIds = $unpaidSettlements->pluck('id')->all();
                            $now = now();

                            // Batch update all locked unpaid settlements
                            ConsignmentSettlement::whereIn('id', $settlementIds)
                                ->where('status', SettlementStatus::Unpaid)
                                ->update([
                                    'status' => SettlementStatus::Paid,
                                    'payout_reference' => $payoutReference,
                                    'paid_at' => $now,
                                ]);

                            // Dispatch event for future cash-ledger & PDF receipt listeners (executed after commit)
                            PayoutExecuted::dispatch($supplierId, $payoutReference, $totalAmount, $settlementIds);

                            Notification::make()
                                ->title('Payout Berhasil Dilakukan')
                                ->body(sprintf('Berhasil melunasi %d tagihan konsinyasi senilai Rp %s (Ref: %s)', count($settlementIds), number_format($totalAmount, 2, ',', '.'), $payoutReference))
                                ->success()
                                ->send();
                        }, 5);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('markAsPaid')
                        ->label('Tandai Terpilih Sebagai Lunas')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->authorize(fn (): bool => (bool) auth()->user()?->hasRole('owner'))
                        ->requiresConfirmation()
                        ->modalHeading('Tandai Tagihan Terpilih Lunas')
                        ->modalDescription('Apakah Anda yakin ingin melunasi seluruh item tagihan konsinyasi yang dipilih?')
                        ->action(function (Collection $records): void {
                            DB::transaction(function () use ($records): void {
                                // Lock and filter eligible unpaid records
                                $unpaidRecords = ConsignmentSettlement::whereIn('id', $records->pluck('id'))
                                    ->where('status', SettlementStatus::Unpaid)
                                    ->lockForUpdate()
                                    ->get();

                                if ($unpaidRecords->isEmpty()) {
                                    Notification::make()
                                        ->title('Tidak ada tagihan belum lunas yang dipilih.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                $bySupplier = $unpaidRecords->groupBy('supplier_id');
                                $today = now()->format('Ymd');
                                $now = now();

                                foreach ($bySupplier as $supplierId => $supplierSettlements) {
                                    $prefix = "PAYOUT-{$supplierId}-{$today}-";

                                    $latestPayout = ConsignmentSettlement::where('payout_reference', 'like', "{$prefix}%")
                                        ->lockForUpdate()
                                        ->orderByDesc('id')
                                        ->first();

                                    $nextSeq = 1;
                                    if ($latestPayout && preg_match('/-(\d{4})$/', $latestPayout->payout_reference, $matches)) {
                                        $nextSeq = ((int) $matches[1]) + 1;
                                    }

                                    $payoutReference = $prefix.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
                                    $totalAmount = (float) $supplierSettlements->sum('amount');
                                    $settlementIds = $supplierSettlements->pluck('id')->all();

                                    ConsignmentSettlement::whereIn('id', $settlementIds)
                                        ->where('status', SettlementStatus::Unpaid)
                                        ->update([
                                            'status' => SettlementStatus::Paid,
                                            'payout_reference' => $payoutReference,
                                            'paid_at' => $now,
                                        ]);

                                    PayoutExecuted::dispatch((int) $supplierId, $payoutReference, $totalAmount, $settlementIds);
                                }

                                Notification::make()
                                    ->title('Tagihan berhasil ditandai lunas')
                                    ->success()
                                    ->send();
                            }, 5);
                        }),
                ]),
            ])
            ->emptyStateHeading('Belum Ada Tagihan Konsinyasi')
            ->emptyStateDescription('Tagihan konsinyasi akan dibuat otomatis saat produk konsinyasi terjual di POS.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConsignmentSettlements::route('/'),
        ];
    }
}
