<?php

namespace App\Http\Resources\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'item_id' => $this->item_id,
            'lot_number' => $this->lot_number,
            'expiry_date' => $this->expiry_date?->toDateString(),
            'quantity_received' => $this->quantity_received,
            'quantity_remaining' => $this->quantity_remaining,
            'received_at' => $this->received_at?->toDateString(),
            'is_expired' => $this->isExpired(),
            'notes' => $this->notes,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
                'contact_person' => $this->supplier->contact_person,
            ] : null,

            'item' => [
                'id' => $this->item?->id,
                'name' => $this->item?->name,
                'sku' => $this->item?->sku,
                'unit_of_measure' => $this->item?->unit_of_measure,
            ],

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
