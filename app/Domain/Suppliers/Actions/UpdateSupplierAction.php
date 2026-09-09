<?php

declare(strict_types=1);

namespace App\Domain\Suppliers\Actions;

use App\Domain\Suppliers\DTOs\SupplierDTO;
use App\Models\Supplier;

class UpdateSupplierAction
{
    public function execute(Supplier $supplier, SupplierDTO $dto): Supplier
    {
        $supplier->update([
            'name'           => $dto->name,
            'contact_person' => $dto->contactPerson,
            'email'          => $dto->email,
            'phone'          => $dto->phone,
            'address'        => $dto->address,
            'tax_id'         => $dto->taxId,
            'payment_terms'  => $dto->paymentTerms,
            'notes'          => $dto->notes,
            'is_active'      => $dto->isActive,
        ]);

        return $supplier->fresh();
    }
}
