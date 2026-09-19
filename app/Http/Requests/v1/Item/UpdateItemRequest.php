<?php

namespace App\Http\Requests\v1\Item;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');
        return $item && $this->user()->can('update', $item);
    }

    public function rules(): array
    {
        $itemId = $this->route('item')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => ['sometimes', 'required', 'string', 'max:100', "unique:items,sku,{$itemId}"],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'unit_of_measure' => ['sometimes', 'required', 'string', 'max:50'],
            'supplier_id' => ['sometimes', 'nullable', 'integer', 'exists:suppliers,id'],
            'minimum_threshold' => ['sometimes', 'required', 'integer', 'min:0'],
            'maximum_threshold' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'unit_cost' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999.99'],
            'storage_location' => ['sometimes', 'nullable', 'string', 'max:150'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
