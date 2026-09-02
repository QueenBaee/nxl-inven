<?php

namespace App\Filament\Pages;

use App\Enums\OpnameStatus;
use App\Enums\SettlementStatus;
use App\Models\StockOpnameItem;
use App\Models\Supplier;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ConsignmentExposureReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'Reports & Analytics';

    protected static ?string $navigationLabel = 'Consignment Summary';

    protected static ?string $title = 'Ringkasan Konsinyasi & Tagihan Supplier';

    protected static string $view = 'filament.pages.consignment-exposure-report';

    protected static ?int $navigationSort = 5;

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
                Supplier::query()
                    ->with(['products', 'consignmentSettlements.transactionItem'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Supplier')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('contact_person')
                    ->label('Kontak / PIC')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('active_skus')
                    ->label('SKU Konsinyasi')
                    ->state(fn (Supplier $record): int => $record->products()->count()),

                Tables\Columns\TextColumn::make('units_sold')
                    ->label('Total Terjual')
                    ->state(function (Supplier $record): int {
                        return (int) $record->consignmentSettlements()
                            ->join('transaction_items', 'consignment_settlements.transaction_item_id', '=', 'transaction_items.id')
                            ->sum('transaction_items.quantity');
                    }),

                Tables\Columns\TextColumn::make('total_generated')
                    ->label('Total Pokok Terbentuk')
                    ->state(fn (Supplier $record): float => (float) $record->consignmentSettlements()->sum('amount'))
                    ->money('IDR'),

                Tables\Columns\TextColumn::make('total_settled')
                    ->label('Sudah Dibayar (Lunas)')
                    ->state(fn (Supplier $record): float => (float) $record->consignmentSettlements()->where('status', SettlementStatus::Paid)->sum('amount'))
                    ->money('IDR')
                    ->color('success'),

                Tables\Columns\TextColumn::make('pending_payable')
                    ->label('Outstanding (Belum Bayar)')
                    ->state(fn (Supplier $record): float => (float) $record->consignmentSettlements()->where('status', SettlementStatus::Unpaid)->sum('amount'))
                    ->money('IDR')
                    ->color(fn (Supplier $record): string => $record->consignmentSettlements()->where('status', SettlementStatus::Unpaid)->exists() ? 'warning' : 'gray')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('opname_liability')
                    ->label('Selisih Opname (Informasional)')
                    ->state(function (Supplier $record): string {
                        // Calculate opname shortage liability on completed audits for this supplier's consignment products
                        $productIds = $record->products()->pluck('id');
                        $items = StockOpnameItem::whereIn('product_id', $productIds)
                            ->where('is_consignment_snapshot', true)
                            ->whereHas('stockOpname', fn ($q) => $q->where('status', OpnameStatus::Completed))
                            ->get();

                        $totalLiability = $items->sum('loss_value');

                        return 'Rp '.number_format((float) $totalLiability, 2, ',', '.');
                    })
                    ->color('gray')
                    ->tooltip('Nilai selisih audit fisik stock opname yang disetujui; dicatat sebagai informasi terpisah dari tagihan penjualan kasir.'),
            ])
            ->emptyStateHeading('Belum Ada Supplier Konsinyasi')
            ->emptyStateDescription('Daftarkan supplier dan produk konsinyasi untuk melihat ringkasan eksposur konsinyasi.')
            ->emptyStateIcon('heroicon-o-briefcase');
    }
}
