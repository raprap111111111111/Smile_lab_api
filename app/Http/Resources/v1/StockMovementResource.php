<?php

namespace App\Http\Resources\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'item_id' => $this->item_id,
            'inventory_batch_id' => $this->inventory_batch_id,

            'type' => $this->type->value,
            'type_label' => $this->type->label(),

            'quantity_delta' => $this->quantity_delta,
            'balance_after' => $this->balance_after,
            // No batch behind an outflow means stock ran short. The client shows
            // this differently from an ordinary withdrawal.
            'is_shortfall' => $this->isShortfall(),

            'reason' => $this->reason,
            'notes' => $this->notes,

            // What caused it. class_basename keeps the app's namespace off the
            // wire while still telling the client what kind of record it was.
            'reference_type' => $this->reference_type ? class_basename($this->reference_type) : null,
            'reference_id' => $this->reference_id,

            'item' => [
                'id' => $this->item?->id,
                'name' => $this->item?->name,
                'sku' => $this->item?->sku,
                'unit_of_measure' => $this->item?->unit_of_measure,
                'is_archived' => $this->item?->trashed() ?? false,
            ],

            'branch' => [
                'id' => $this->branch?->id,
                'name' => $this->branch?->name,
                'branch_code' => $this->branch?->branch_code,
            ],

            'batch' => $this->inventory_batch_id === null ? null : [
                'id' => $this->batch?->id,
                'lot_number' => $this->batch?->lot_number,
                'expiry_date' => $this->batch?->expiry_date?->toDateString(),
            ],

            'performed_by' => $this->performed_by === null ? null : [
                'id' => $this->performer?->id,
                'name' => $this->performer?->name,
            ],

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
