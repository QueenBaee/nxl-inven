<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * StockOpnameItem Model
 *
 * @property int $id
 * @property int $stock_opname_id
 * @property int $product_id
 * @property int $system_qty Snapshot of products.stock at session start
 * @property string $cost_price_snapshot Snapshot of products.cost_price at session start
 * @property int|null $physical_qty Entered by staff during blind count audit
 * @property bool $is_consignment_snapshot Snapshot of product.type === consignment at session start
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $variance physical_qty - system_qty (null while uncounted)
 * @property-read float $loss_value Absolute monetary loss/liability for negative variances
 * @property-read StockOpname $stockOpname
 * @property-read Product $product
 */
class StockOpnameItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'stock_opname_id',
        'product_id',
        'system_qty',
        'cost_price_snapshot',
        'physical_qty',
        'is_consignment_snapshot',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'system_qty' => 'integer',
            'cost_price_snapshot' => 'decimal:2',
            'physical_qty' => 'integer',
            'is_consignment_snapshot' => 'boolean',
        ];
    }

    /**
     * Compute variance (physical_qty - system_qty). Null when uncounted.
     */
    protected function variance(): Attribute
    {
        return Attribute::make(
            get: fn (): ?int => $this->physical_qty !== null ? (int) $this->physical_qty - (int) $this->system_qty : null,
        );
    }

    /**
     * Compute monetary loss/liability value when physical quantity is less than system count with BCMath precision.
     */
    protected function lossValue(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                $variance = $this->variance;
                if ($variance === null || $variance >= 0) {
                    return 0.0;
                }

                return (float) bcmul((string) $this->cost_price_snapshot, (string) abs($variance), 2);
            },
        );
    }

    /**
     * Differentiates shop's own direct financial loss from liability owed to a consignment supplier.
     */
    public function varianceType(): string
    {
        return $this->is_consignment_snapshot ? 'supplier_liability' : 'shop_loss';
    }

    /**
     * Get the stock opname session this item belongs to.
     */
    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    /**
     * Get the product being audited.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the stock movements created during reconciliation for this item.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
