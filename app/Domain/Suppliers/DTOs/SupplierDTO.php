<?php

declare(strict_types=1);

namespace App\Domain\Suppliers\DTOs;

readonly class SupplierDTO
{
    public function __construct(
        public string $name,
        public ?string $contactPerson = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $address = null,
        public ?string $taxId = null,
        public ?string $paymentTerms = null,
        public ?string $notes = null,
        public bool $isActive = true,
    ) {}
}
