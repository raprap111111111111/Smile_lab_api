<?php

declare(strict_types=1);

namespace App\Domain\Suppliers\Mappers;

use App\Domain\Suppliers\DTOs\SupplierDTO;
use Illuminate\Http\Request;

class SupplierMapper
{
    public static function fromRequest(Request $request): SupplierDTO
    {
        return new SupplierDTO(
            name: (string) $request->input('name'),
            contactPerson: $request->input('contact_person'),
            email: $request->input('email'),
            phone: $request->input('phone'),
            address: $request->input('address'),
            taxId: $request->input('tax_id'),
            paymentTerms: $request->input('payment_terms'),
            notes: $request->input('notes'),
            isActive: (bool) $request->input('is_active', true),
        );
    }
}
