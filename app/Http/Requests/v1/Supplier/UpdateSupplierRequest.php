<?php

declare(strict_types=1);

namespace App\Http\Requests\v1\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('supplier.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email'          => ['nullable', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'address'        => ['nullable', 'string', 'max:1000'],
            'tax_id'         => ['nullable', 'string', 'max:100'],
            'payment_terms'  => ['nullable', 'string', 'max:100'],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'is_active'      => ['nullable', 'boolean'],
        ];
    }
}
