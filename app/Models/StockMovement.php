<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line of the stock ledger. Append-only: rows are written by StockLedger and
 * never updated or deleted, which is what lets `inventories.quantity` be defined
 * as the sum of quantity_delta rather than a number someone overwrote.
 */
class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'item_id',
        'inventory_batch_id',
        'type',
        'quantity_delta',
        'balance_after',
        'reason',
        'reference_type',
        'reference_id',
        'performed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type'           => StockMovementType::class,
            'quantity_delta' => 'integer',
            'balance_after'  => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /** What caused this movement — an Appointment, for automatic consumption. */
    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    /**
     * A movement that no batch could satisfy.
     *
     * Consumption is never blocked on stock, so when supplies run short the
     * unmet remainder is still recorded — with no batch, because there was
     * nothing physical to draw from.
     */
    public function isShortfall(): bool
    {
        return $this->inventory_batch_id === null && $this->quantity_delta < 0;
    }

    public function scopeForStock(Builder $query, int $branchId, int $itemId): Builder
    {
        return $query->where('branch_id', $branchId)->where('item_id', $itemId);
    }

    /** Movements caused by a given record, e.g. one appointment's deduction. */
    public function scopeCausedBy(Builder $query, string $type, int $id): Builder
    {
        return $query->where('reference_type', $type)->where('reference_id', $id);
    }
}
