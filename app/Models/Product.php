<?php

namespace App\Models;

use App\Enums\ProductType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * Product Model
 *
 * @property int $id
 * @property string $sku
 * @property string $name
 * @property int $stock
 * @property ProductType $type
 * @property int|null $supplier_id
 * @property string $cost_price Business-critical: For consignment products, cost_price represents the exact amount owed to the supplier per unit when sold.
 * @property string $selling_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier|null $supplier
 * @property-read Collection<int, StockMovement> $stockMovements
 * @property-read Collection<int, TransactionItem> $transactionItems
 * @property-read Collection<int, StockOpnameItem> $stockOpnameItems
 */
class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sku',
        'name',
        'stock',
        'type',
        'supplier_id',
        'cost_price',
        'selling_price',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'type' => ProductType::class,
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
        ];
    }

    /**
     * The "booted" method of the model.
     *
     * Enforces model-level guard ensuring consignment products always have an assigned supplier.
     */
    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if ($product->type === ProductType::Consignment && blank($product->supplier_id)) {
                throw new InvalidArgumentException('Consignment products must have an assigned supplier.');
            }
        });
    }

    /**
     * Get the supplier of the product.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the stock movements recorded for the product.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get the sales transaction items recorded for the product.
     */
    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    /**
     * Get the stock opname items recorded for the product.
     */
    public function stockOpnameItems(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }
}
