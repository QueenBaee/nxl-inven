<?php

namespace App\Filament\Resources;

use App\Actions\StockOpname\ApproveStockOpnameAction;
use App\Actions\StockOpname\ReopenStockOpnameCountAction;
use App\Actions\StockOpname\StartStockOpnameAction;
use App\Enums\OpnameStatus;
use App\Filament\Resources\StockOpnameResource\Pages;
use App\Filament\Resources\StockOpnameResource\RelationManagers\ItemsRelationManager;
use App\Models\StockOpname;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StockOpnameResource extends Resource
{
    protected static ?string $model = StockOpname::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock Opname';

    protected static ?string $modelLabel = 'Stock Opname Session';

    protected static ?string $pluralModelLabel = 'Stock Opname Sessions';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detail Sesi Stock Opname')
                    ->description('Buat sesi audit fisik stok toko.')
                    ->schema([
                        Forms\Components\TextInput::make('session_name')
                            ->label('Nama Sesi Audit')
                            ->required()
                            ->placeholder('Contoh: Audit Bulanan - September 2026')
                            ->maxLength(255),

                        Forms\Components\Select::make('status')
                            ->label('Status Sesi')
                            ->options(OpnameStatus::class)
                            ->default(OpnameStatus::Draft->value)
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\Hidden::make('created_by')
                            ->default(fn (): ?int => Auth::id()),
                    ])
                    ->columns(2),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi Sesi Stock Opname')
                    ->schema([
                        Infolists\Components\TextEntry::make('session_name')
                            ->label('Nama Sesi Audit')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Status Database')
                            ->badge(),

                        Infolists\Components\TextEntry::make('creator.name')
                            ->label('Dibuat Oleh')
                            ->placeholder('Sistem'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Waktu Dibuat')
                            ->dateTime('d M Y H:i'),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Progress Penghitungan Fisik & Review')
                    ->schema([
                        Infolists\Components\TextEntry::make('total_items')
                            ->label('Total Produk')
                            ->state(fn (StockOpname $record): int => $record->items()->count())
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('counted_items')
                            ->label('Sudah Dihitung')
                            ->state(fn (StockOpname $record): int => $record->items()->whereNotNull('physical_qty')->count())
                            ->color('success')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('uncounted_items')
                            ->label('Belum Dihitung')
                            ->state(fn (StockOpname $record): int => $record->items()->whereNull('physical_qty')->count())
                            ->color('warning')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('progress_status')
                            ->label('Status Handoff / Kemajuan')
                            ->state(function (StockOpname $record): string {
                                if ($record->status === OpnameStatus::Completed) {
                                    return 'Selesai — Stok Terekonsiliasi';
                                }

                                $total = $record->items()->count();
                                $counted = $record->items()->whereNotNull('physical_qty')->count();

                                if ($record->isCountingSubmitted()) {
                                    $submitter = $record->countingCompletedBy?->name ?? 'Kasir';
                                    $time = $record->counting_completed_at?->format('d/m/Y H:i') ?? '-';

                                    return "Penghitungan Selesai (oleh {$submitter} pada {$time}) — Menunggu Review Owner";
                                }

                                if ($total > 0 && $counted === $total) {
                                    return "{$counted} dari {$total} produk (100%) — Siap dikirim untuk review";
                                }

                                $percent = $total > 0 ? round(($counted / $total) * 100) : 0;

                                return "{$counted} dari {$total} produk ({$percent}%) — Sedang Dihitung";
                            })
                            ->badge()
                            ->color(function (StockOpname $record): string {
                                if ($record->status === OpnameStatus::Completed) {
                                    return 'success';
                                }
                                if ($record->isCountingSubmitted()) {
                                    return 'info';
                                }
                                $total = $record->items()->count();
                                $counted = $record->items()->whereNotNull('physical_qty')->count();

                                return ($total > 0 && $counted === $total) ? 'success' : 'warning';
                            }),
                    ])
                    ->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('session_name')
                    ->label('Nama Sesi')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('operational_status')
                    ->label('Status Operasional')
                    ->state(function (StockOpname $record): string {
                        return match ($record->status) {
                            OpnameStatus::Draft => 'Draft',
                            OpnameStatus::InProgress => $record->isCountingSubmitted() ? 'Menunggu Review Owner' : 'Sedang Dihitung',
                            OpnameStatus::Completed => 'Selesai (Terekonsiliasi)',
                        };
                    })
                    ->badge()
                    ->color(function (StockOpname $record): string {
                        return match ($record->status) {
                            OpnameStatus::Draft => 'gray',
                            OpnameStatus::InProgress => $record->isCountingSubmitted() ? 'info' : 'warning',
                            OpnameStatus::Completed => 'success',
                        };
                    })
                    ->sortable(false),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status DB')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->sortable(),

                Tables\Columns\TextColumn::make('countingCompletedBy.name')
                    ->label('Dihitung Oleh')
                    ->placeholder('Belum Submit')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('approver.name')
                    ->label('Disetujui Oleh')
                    ->placeholder('Menunggu Approval')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                // Staff Action: Lanjutkan Hitung Fisik (Blind Count)
                Tables\Actions\ViewAction::make('countOpname')
                    ->label(fn (StockOpname $record): string => $record->isCountingSubmitted() ? 'Lihat Status Hitung' : 'Lanjutkan Hitung')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color(fn (StockOpname $record): string => $record->isCountingSubmitted() ? 'gray' : 'warning')
                    ->visible(fn (StockOpname $record): bool => $record->status === OpnameStatus::InProgress && ! (Auth::user()?->hasRole('owner') ?? false)),

                // Owner Action: Review Opname / Detail Sesi
                Tables\Actions\ViewAction::make('reviewOpname')
                    ->label(fn (StockOpname $record): string => $record->status === OpnameStatus::InProgress ? ($record->isCountingSubmitted() ? 'Review Hasil Hitung' : 'Pantau Hitung') : 'Lihat Detail')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn (): bool => Auth::user()?->hasRole('owner') ?? false),

                // Owner Action: Edit Draft Session Name
                Tables\Actions\EditAction::make()
                    ->label('Edit Draft')
                    ->visible(fn (StockOpname $record): bool => $record->status === OpnameStatus::Draft && (Auth::user()?->hasRole('owner') ?? false)),

                // Owner Action: Transition draft -> in_progress
                Tables\Actions\Action::make('startOpname')
                    ->label('Mulai Audit')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->authorize('start')
                    ->visible(fn (StockOpname $record): bool => $record->status === OpnameStatus::Draft)
                    ->requiresConfirmation()
                    ->modalHeading('Mulai Sesi Stock Opname')
                    ->modalDescription('Tindakan ini akan mengunci pergerakan stok (inbound & kasir) untuk seluruh produk yang diaudit selama proses audit fisik berlangsung. Lanjutkan?')
                    ->action(function (StockOpname $record): void {
                        try {
                            $action = app(StartStockOpnameAction::class);
                            $updatedSession = $action->execute($record);

                            Notification::make()
                                ->title('Sesi Opname Dimulai')
                                ->body(sprintf('Berhasil mengambil snapshot %d produk. Stok produk yang diaudit kini dibekukan.', $updatedSession->items()->count()))
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Gagal Memulai Sesi')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // Owner Action: Reopen Counting for Staff Corrections
                Tables\Actions\Action::make('reopenOpname')
                    ->label('Buka Kembali Hitung')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->authorize('reopenCount')
                    ->visible(fn (StockOpname $record): bool => (Auth::user()?->hasRole('owner') ?? false) && $record->status === OpnameStatus::InProgress && $record->isCountingSubmitted())
                    ->requiresConfirmation()
                    ->modalHeading('Buka Kembali Sesi Penghitungan?')
                    ->modalDescription('Membuka kembali sesi akan mengizinkan staf kasir untuk memperbaiki Qty Fisik. Nilai hitung fisik yang sudah dimasukkan tidak akan dihapus.')
                    ->action(function (StockOpname $record): void {
                        try {
                            $action = app(ReopenStockOpnameCountAction::class);
                            $action->execute($record);

                            Notification::make()
                                ->title('Penghitungan Dibuka Kembali')
                                ->body('Sesi penghitungan fisik dibuka kembali untuk perbaikan data.')
                                ->warning()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Gagal Membuka Sesi')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // Owner Action: Transition in_progress -> completed
                Tables\Actions\Action::make('approveOpname')
                    ->label('Setujui & Sync Stok')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->authorize('approve')
                    ->visible(fn (StockOpname $record): bool => (Auth::user()?->hasRole('owner') ?? false) && $record->status === OpnameStatus::InProgress && $record->isCountingSubmitted())
                    ->requiresConfirmation()
                    ->modalHeading('Setujui & Rekonsiliasi Stok Opname')
                    ->modalDescription('Persetujuan akan menyelaraskan stok sistem dengan hasil hitung fisik dan membuat mutasi penyesuaian (adjustment).')
                    ->action(function (StockOpname $record): void {
                        try {
                            $approverId = (int) (Auth::id() ?? $record->created_by);
                            $action = app(ApproveStockOpnameAction::class);
                            $action->execute($record, $approverId);

                            Notification::make()
                                ->title('Stock Opname Disetujui')
                                ->body('Stok produk berhasil diselaraskan dan direkonsiliasi.')
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Persetujuan Ditolak')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->emptyStateHeading('Belum Ada Sesi Stock Opname')
            ->emptyStateDescription('Mulai buat sesi stock opname baru untuk melakukan audit fisik stok toko.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
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
            'index' => Pages\ListStockOpnames::route('/'),
            'create' => Pages\CreateStockOpname::route('/create'),
            'view' => Pages\ViewStockOpname::route('/{record}'),
            'edit' => Pages\EditStockOpname::route('/{record}/edit'),
        ];
    }
}
