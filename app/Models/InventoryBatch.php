<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One delivery of one item into one branch.
 *
 * Note the absence of the LogsActivity trait, which Inventory and Item both
 * use. Consumption touches quantity_remaining on every batch it draws from, and
 * mirroring that into activity_logs would double-write the audit trail that
 * stock_movements already keeps in a far more useful shape.
 */
class InventoryBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'item_id',
        'supplier_id',
        'lot_number',
        'expiry_date',
        'quantity_received',
        'quantity_remaining',
        'received_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'supplier_id'        => 'integer',
            'expiry_date'        => 'date',
            'received_at'        => 'date',
            'quantity_received'  => 'integer',
            'quantity_remaining' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Batches with something left to draw from. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('quantity_remaining', '>', 0);
    }

    /**
     * First-expired, first-out.
     *
     * Batches with no expiry are non-perishable and sort last — consuming them
     * ahead of a carpule that expires next month would waste the carpule. Ties
     * break on id, so the oldest delivery goes first.
     */
    public function scopeFefo(Builder $query): Builder
    {
        return $query
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('id');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function isExpiringWithin(int $days): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }

        return $this->expiry_date->isBetween(now(), now()->addDays($days));
    }
}
