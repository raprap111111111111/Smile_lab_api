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
            'minimum_threshold' => ['sometimes', 'required', 'integer', 'min:0'],
            'maximum_threshold' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
