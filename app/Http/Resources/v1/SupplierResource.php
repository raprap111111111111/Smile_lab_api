<?php

declare(strict_types=1);

namespace App\Http\Resources\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'contact_person' => $this->contact_person,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'address'        => $this->address,
            'tax_id'         => $this->tax_id,
            'payment_terms'  => $this->payment_terms,
            'notes'          => $this->notes,
            'is_active'      => (bool) $this->is_active,
            'items_count'    => $this->items_count ?? $this->items()->count(),
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
        ];
    }
}
