<?php

declare(strict_types=1);

namespace App\Http\Requests\v1\Inventory;

use App\Http\Requests\Concerns\ChecksBranchAccess;
use App\Models\InventoryBatch;
use Illuminate\Foundation\Http\FormRequest;

class WriteOffStockRequest extends FormRequest
{
    use ChecksBranchAccess;

    protected function prepareForValidation(): void
    {
        $batchId = $this->input('batch_id');

        if ($batchId !== null && is_numeric($batchId)) {
            $batch = InventoryBatch::find((int) $batchId);
            if ($batch !== null) {
                $merge = [];
                if (! $this->has('item_id')) {
                    $merge['item_id'] = $batch->item_id;
                }
                if (! $this->has('branch_id')) {
                    $merge['branch_id'] = $batch->branch_id;
                }
                if (! empty($merge)) {
                    $this->merge($merge);
                }
            }
        }
    }

    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        // Disposal of stock requires either adjust or stock-out authority
        $hasPermission = $user->can('inventory.adjust') || $user->can('inventory.stock-out');
        if (! $hasPermission) {
            return false;
        }

        return $this->canAccessBranch($this->input('branch_id'));
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'item_id'   => ['required', 'integer', 'exists:items,id'],
            'quantity'  => ['required', 'integer', 'min:1'],
            'batch_id'  => ['nullable', 'integer', 'exists:inventory_batches,id'],
            'reason'    => ['required', 'string', 'max:255'],
            'notes'     => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required to write off inventory stock.',
            'quantity.min'    => 'Write-off quantity must be at least 1.',
        ];
    }
}
