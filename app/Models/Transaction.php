<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Enums\TransactionStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Transaction Model
 *
 * @property int $id
 * @property string $invoice_number
 * @property string $total_amount
 * @property PaymentMethod $payment_method
 * @property SalesChannel $channel
 * @property TransactionStatus $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, TransactionItem> $items
 * @property-read Collection<int, StockMovement> $stockMovements
 * @property-read User|null $creator
 */
class Transaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'invoice_number',
        'total_amount',
        'payment_method',
        'channel',
        'status',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'channel' => SalesChannel::class,
            'status' => TransactionStatus::class,
        ];
    }

    /**
     * Determine whether the transaction represents an uncollected marketplace receivable.
     * Returns true when channel is not offline (e.g. Shopee, Tokopedia), pending disbursement.
     */
    public function isReceivable(): bool
    {
        return $this->channel !== SalesChannel::Offline;
    }

    /**
     * Get the items included in the transaction.
     */
    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    /**
     * Get the stock movements recorded for this transaction.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get the cashier / user who processed the sale.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
