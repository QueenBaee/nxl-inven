<?php

namespace App\Actions\StockOpname;

use App\Enums\OpnameStatus;
use App\Models\StockOpname;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReopenStockOpnameCountAction
{
    /**
     * Reopen physical counting for staff corrections.
     *
     * @throws ValidationException
     */
    public function execute(StockOpname $session): StockOpname
    {
        return DB::transaction(function () use ($session): StockOpname {
            /** @var StockOpname $lockedSession */
            $lockedSession = StockOpname::where('id', $session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSession->status !== OpnameStatus::InProgress) {
                throw ValidationException::withMessages([
                    'session' => "Hanya sesi berstatus 'in_progress' yang dapat dibuka kembali.",
                ]);
            }

            if ($lockedSession->counting_completed_at === null) {
                throw ValidationException::withMessages([
                    'session' => 'Penghitungan fisik masih dalam status aktif dan belum dikirimkan.',
                ]);
            }

            $lockedSession->update([
                'counting_completed_at' => null,
                'counting_completed_by' => null,
            ]);

            return $lockedSession;
        }, 5);
    }
}

