<?php

namespace App\Models;

use App\Enums\OpnameStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * StockOpname Model
 *
 * @property int $id
 * @property string $session_name
 * @property OpnameStatus $status
 * @property Carbon|null $counting_completed_at
 * @property int|null $counting_completed_by
 * @property int $created_by
 * @property int|null $approved_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, StockOpnameItem> $items
 * @property-read User $creator
 * @property-read User|null $approver
 * @property-read User|null $countingCompletedBy
 */
class StockOpname extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'session_name',
        'status',
        'counting_completed_at',
        'counting_completed_by',
        'created_by',
        'approved_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OpnameStatus::class,
            'counting_completed_at' => 'datetime',
        ];
    }

    /**
     * Check if physical count has been submitted for owner review.
     */
    public function isCountingSubmitted(): bool
    {
        return $this->counting_completed_at !== null;
    }

    /**
     * Check if all items in this session have been physically counted.
     */
    public function allCounted(): bool
    {
        return $this->items()->whereNull('physical_qty')->doesntExist();
    }

    /**
     * Get the line items snapshotted and counted for this opname session.
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    /**
     * Get the user who initiated the opname session.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved the opname session.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who finalized and submitted the physical counting.
     */
    public function countingCompletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counting_completed_by');
    }
}
