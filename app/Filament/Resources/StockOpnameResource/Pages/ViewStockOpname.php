<?php

namespace App\Filament\Resources\StockOpnameResource\Pages;

use App\Actions\StockOpname\ApproveStockOpnameAction;
use App\Actions\StockOpname\ReopenStockOpnameCountAction;
use App\Actions\StockOpname\SubmitStockOpnameCountAction;
use App\Enums\OpnameStatus;
use App\Filament\Resources\StockOpnameResource;
use App\Models\StockOpname;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ViewStockOpname extends ViewRecord
{
    protected static string $resource = StockOpnameResource::class;

    public function getTitle(): string
    {
        return 'Audit Fisik Stock Opname: '.($this->record->session_name ?? 'Detail');
    }

    protected function getHeaderActions(): array
    {
        return [
            // Action 1: Staff / Counter Submits Finished Count
            Actions\Action::make('submitCounting')
                ->label('Selesai Menghitung')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('success')
                ->visible(fn (StockOpname $record): bool => $record->status === OpnameStatus::InProgress && ! $record->isCountingSubmitted())
                ->requiresConfirmation()
                ->modalHeading('Selesai Menghitung?')
                ->modalDescription('Setelah dikirim untuk review, Qty Fisik tidak dapat diubah oleh kasir sampai penghitungan dibuka kembali oleh owner. Pastikan seluruh produk fisik telah dihitung.')
                ->modalSubmitActionLabel('Ya, Selesai Menghitung')
                ->modalCancelActionLabel('Batal')
                ->action(function (StockOpname $record): void {
                    try {
                        $action = app(SubmitStockOpnameCountAction::class);
                        $action->execute($record, (int) Auth::id());

                        Notification::make()
                            ->title('Penghitungan Berhasil Dikirim')
                            ->body('Hasil hitung fisik telah dikirimkan dan menunggu review Owner.')
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Pengiriman Ditolak')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // Action 2: Owner Reopens Counting for Corrections
            Actions\Action::make('reopenCounting')
                ->label('Buka Kembali Penghitungan')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (StockOpname $record): bool => (Auth::user()?->hasRole('owner') ?? false) && $record->status === OpnameStatus::InProgress && $record->isCountingSubmitted())
                ->requiresConfirmation()
                ->modalHeading('Buka Kembali Sesi Penghitungan?')
                ->modalDescription('Membuka kembali sesi akan mengizinkan staf kasir untuk memperbaiki Qty Fisik. Nilai hitung fisik yang sudah dimasukkan tidak akan dihapus.')
                ->modalSubmitActionLabel('Buka Kembali')
                ->modalCancelActionLabel('Batal')
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

            // Action 3: Owner Approves Session & Reconciles Stock
            Actions\Action::make('approveOpname')
                ->label('Setujui & Sync Stok')
                ->icon('heroicon-o-check-badge')
                ->color('success')
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
        ];
    }
}
