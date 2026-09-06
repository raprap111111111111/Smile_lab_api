<?php

declare(strict_types=1);

namespace App\Http\Requests\v1\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetInventoryBatchesRequest extends FormRequest
{
    private const MAX_LIMIT = 250;

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('limit')) {
            $merge['limit'] = min(max(1, (int) $this->input('limit')), self::MAX_LIMIT);
        }

        if ($this->has('open_only')) {
            $merge['open_only'] = filter_var($this->input('open_only'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $this->input('open_only');
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('inventory.viewAny');
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'item_id'   => ['nullable', 'integer', 'exists:items,id'],
            'open_only' => ['nullable', 'boolean'],
            'search'    => ['nullable', 'string', 'max:100'],
            'offset'    => ['nullable', 'integer', 'min:0'],
            'limit'     => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
            'order_by'  => ['nullable', Rule::in(['id', 'expiry_date', 'received_at', 'quantity_remaining'])],
            'order_dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}

