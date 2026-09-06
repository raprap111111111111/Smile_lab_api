<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'branch_id',
        'item_id',
        'quantity',
        'expiry_date',
        'last_low_stock_alert_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'expiry_date' => 'date',
        'last_low_stock_alert_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /**
     * The lots behind this summary row.
     *
     * `inventories` is keyed on branch+item rather than owning an id the batches
     * point at, so this is matched on both columns rather than a plain hasMany.
     */
    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class, 'item_id', 'item_id')
            ->where('inventory_batches.branch_id', $this->branch_id);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'item_id', 'item_id')
            ->where('stock_movements.branch_id', $this->branch_id);
    }

    /**
     * Helper to verify if the physical quantity has fallen below the safety threshold
     */
    public function isLowStock(): bool
    {
        return $this->quantity <= ($this->item?->minimum_threshold ?? 10);
    }

    /**
     * Helper to verify if the stock batch has expired
     */
    public function isExpired(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }
        
        return $this->expiry_date->isPast();
    }
}
