<?php

namespace App\Http\Resources\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'category' => $this->category,
            'unit_of_measure' => $this->unit_of_measure,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
                'contact_person' => $this->supplier->contact_person,
                'phone' => $this->supplier->phone,
            ] : null,
            'minimum_threshold' => $this->minimum_threshold,
            'maximum_threshold' => $this->maximum_threshold,
            'is_archived' => $this->trashed(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
