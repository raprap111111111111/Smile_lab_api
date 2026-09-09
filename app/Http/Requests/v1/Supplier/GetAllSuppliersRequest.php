<?php

declare(strict_types=1);

namespace App\Http\Requests\v1\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class GetAllSuppliersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('supplier.viewAny') ?? false;
    }

    public function rules(): array
    {
        return [
            'search'    => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
            'order_by'  => ['nullable', 'string'],
            'order_dir' => ['nullable', 'in:asc,desc'],
        ];
    }
}
