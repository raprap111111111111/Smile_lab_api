<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'category',
        'unit_of_measure',
        'minimum_threshold',
        'maximum_threshold',
    ];

    protected $casts = [
        'minimum_threshold' => 'integer',
        'maximum_threshold' => 'integer',
        'deleted_at' => 'datetime',
    ];

    /**
     * Per-branch stock rows for this item.
     *
     * The inverse of Inventory::item(). Without it there was no way to ask
     * whether an item was still stocked anywhere before deleting it.
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * Active and archived stock batches for this item.
     */
    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }
}
