<?php

namespace App\Models;

use App\Observers\TransactionItemObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * TransactionItem Model
 *
 * @property int $id
 * @property int $transaction_id
 * @property int $product_id
 * @property int $quantity
 * @property string $price
 * @property string $cost_price
 * @property bool $is_consignment Historical snapshot of whether product was consignment at sale time, avoiding mutable relationship lookups.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $subtotal
 * @property-read Transaction $transaction
 * @property-read Product $product
 * @property-read ConsignmentSettlement|null $consignmentSettlement
 */
#[ObservedBy([TransactionItemObserver::class])]
class TransactionItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'transaction_id',
        'product_id',
        'quantity',
        'price',
        'cost_price',
        'is_consignment',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'is_consignment' => 'boolean',
        ];
    }

    /**
     * Get the computed subtotal (quantity * price) to prevent database data drift.
     */
    protected function subtotal(): Attribute
    {
        return Attribute::make(
            get: fn (): string => bcmul((string) $this->price, (string) $this->quantity, 2),
        );
    }

    /**
     * Get the transaction that owns this item.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the product associated with this item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the consignment settlement generated for this item if consignment.
     */
    public function consignmentSettlement(): HasOne
    {
        return $this->hasOne(ConsignmentSettlement::class);
    }
}
