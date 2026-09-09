<?php

namespace App\Domain\Inventories\DTOs;

/** A delivery arriving at a branch. */
final readonly class StockInDTO
{
    public function __construct(
        public int $branchId,
        public int $itemId,
        public int $quantity,
        public ?int $supplierId = null,
        public ?string $lotNumber = null,
        public ?string $expiryDate = null,
        public ?string $receivedAt = null,
        public ?string $reason = null,
        public ?string $notes = null,
        public ?int $performedBy = null,
    ) {}
}
