<?php

namespace App\Http\Requests\v1\Inventory;

use App\Http\Requests\Concerns\ChecksBranchAccess;
use Illuminate\Foundation\Http\FormRequest;

class StockInRequest extends FormRequest
{
    use ChecksBranchAccess;

    public function authorize(): bool
    {
        return $this->user()->can('inventory.stock-in')
            && $this->canAccessBranch($this->input('branch_id'));
    }

    public function rules(): array
    {
        return [
            'branch_id'   => ['required', 'integer', 'exists:branches,id'],
            'item_id'     => ['required', 'integer', 'exists:items,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'quantity'    => ['required', 'integer', 'min:1'],
            'lot_number'  => ['nullable', 'string', 'max:100'],
            // Stock already past its date has no business entering the shelf.
            'expiry_date' => ['nullable', 'date', 'after_or_equal:today'],
            // A delivery can be recorded late, so this may be backdated — but
            // not postdated, which would hide it from today's stock.
            'received_at' => ['nullable', 'date', 'before_or_equal:today'],
            'reason'      => ['nullable', 'string', 'max:255'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'expiry_date.after_or_equal' => 'That expiry date has already passed — expired stock cannot be received.',
            'received_at.before_or_equal' => 'A delivery cannot be recorded for a future date.',
        ];
    }
}
