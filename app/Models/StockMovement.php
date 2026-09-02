<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Observers\StockMovementObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StockMovement Model
 *
 * @property int $id
 * @property int $product_id
 * @property int $quantity
 * @property StockMovementType $type
 * @property string|null $reference_note
 * @property int|null $stock_opname_item_id
 * @property int|null $transaction_id Relational link to the POS transaction for sales deductions
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read StockOpnameItem|null $stockOpnameItem
 * @property-read Transaction|null $transaction
 * @property-read User|null $creator
 */
#[ObservedBy([StockMovementObserver::class])]
class StockMovement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'quantity',
        'type',
        'reference_note',
        'stock_opname_item_id',
        'transaction_id',
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
            'quantity' => 'integer',
            'type' => StockMovementType::class,
        ];
    }

    /**
     * Get the product associated with the stock movement.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the stock opname item that triggered this adjustment movement (if applicable).
     */
    public function stockOpnameItem(): BelongsTo
    {
        return $this->belongsTo(StockOpnameItem::class);
    }

    /**
     * Get the transaction associated with this sale stock deduction (if applicable).
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the user who recorded the stock movement.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
