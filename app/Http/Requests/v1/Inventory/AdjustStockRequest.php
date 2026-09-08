<?php

namespace App\Http\Requests\v1\Inventory;

use App\Http\Requests\Concerns\ChecksBranchAccess;
use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    use ChecksBranchAccess;

    protected function prepareForValidation(): void
    {
        if ($this->has('quantity') && ! $this->has('counted_quantity')) {
            $this->merge([
                'counted_quantity' => $this->input('quantity'),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('inventory.adjust')
            && $this->canAccessBranch($this->input('branch_id'));
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'item_id'   => ['required', 'integer', 'exists:items,id'],
            // What was physically counted, not the difference.
            'counted_quantity' => ['required', 'integer', 'min:0'],
            // Required, not nullable: an adjustment asserts that reality
            // disagrees with the ledger, and that is precisely the entry someone
            // will want explained six months from now.
            'reason'      => ['required', 'string', 'max:255'],
            // Only used when counting UP, since the surplus units need a lot to
            // live in before FEFO can ever draw them.
            'lot_number'  => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Give a reason for the adjustment — it is the only record of why the count changed.',
        ];
    }
}
