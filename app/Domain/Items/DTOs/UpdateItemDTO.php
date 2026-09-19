<?php

namespace App\Domain\Items\DTOs;

final readonly class UpdateItemDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $sku = null,
        public ?string $category = null,
        public ?string $unitOfMeasure = null,
        public ?int $minimumThreshold = null,
        public ?int $maximumThreshold = null,
        public ?int $supplierId = null,
        public bool $hasSupplierId = false,
        public bool $hasMaximumThreshold = false,
        public ?float $unitCost = null,
        public ?string $storageLocation = null,
        public bool $hasStorageLocation = false,
        public ?string $notes = null,
        public bool $hasNotes = false,
    ) {}
}
