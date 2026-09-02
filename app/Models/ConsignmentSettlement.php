<?php

namespace App\Models;

use App\Enums\SettlementStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ConsignmentSettlement Model
 *
 * @property int $id
 * @property int $supplier_id Snapshot of supplier_id at settlement generation time
 * @property int $transaction_item_id
 * @property string $amount Calculated from historical cost_price * quantity snapshot
 * @property SettlementStatus $status
 * @property string|null $payout_reference Batch identifier for grouped payouts
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @property-read TransactionItem $transactionItem
 */
class ConsignmentSettlement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'supplier_id',
        'transaction_item_id',
        'amount',
        'status',
        'payout_reference',
        'paid_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => SettlementStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the supplier owed for this consignment settlement.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the transaction item that generated this settlement.
     */
    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class);
    }
}
