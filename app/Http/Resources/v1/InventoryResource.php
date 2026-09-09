<?php

namespace App\Http\Resources\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'item_id' => $this->item_id,
            'quantity' => $this->quantity,
            'expiry_date' => $this->expiry_date?->toDateString(),
            'is_low_stock' => $this->isLowStock(),
            'is_expired' => $this->isExpired(),
            
            // Nested Item Details
            'item' => [
                'id' => $this->item?->id,
                'name' => $this->item?->name,
                'sku' => $this->item?->sku,
                'category' => $this->item?->category,
                'unit_of_measure' => $this->item?->unit_of_measure,
                'supplier_id' => $this->item?->supplier_id,
                'supplier' => $this->item?->supplier ? [
                    'id' => $this->item->supplier->id,
                    'name' => $this->item->supplier->name,
                    'contact_person' => $this->item->supplier->contact_person,
                    'phone' => $this->item->supplier->phone,
                ] : null,
                'minimum_threshold' => $this->item?->minimum_threshold,
                'maximum_threshold' => $this->item?->maximum_threshold,
            ],

            // Nested Branch Details
            'branch' => [
                'id' => $this->branch?->id,
                'name' => $this->branch?->name,
                'branch_code' => $this->branch?->branch_code,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
