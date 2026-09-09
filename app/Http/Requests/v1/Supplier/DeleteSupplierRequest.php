<?php

declare(strict_types=1);

namespace App\Http\Requests\v1\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class DeleteSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('supplier.delete') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
