<?php

namespace App\Domain\Inventories\DTOs;

use App\Enums\StockMovementType;

/**
 * One request to change stock. Every write to the ledger is expressed as one of
 * these, whatever triggered it.
 */
final readonly class RecordMovementDTO
{
    public function __construct(
        public int $branchId,
        public int $itemId,
        public StockMovementType $type,

        /**
         * Signed. Positive adds stock and opens a batch; negative draws from
         * existing batches, earliest expiry first.
         */
        public int $quantityDelta,

        // Batch details, used only when adding stock.
        public ?int $supplierId = null,
        public ?string $lotNumber = null,
        public ?string $expiryDate = null,
        public ?string $receivedAt = null,

        public ?string $reason = null,

        // What caused this — Appointment::class and its id, for automatic
        // consumption. Also what makes the deduction idempotent.
        public ?string $referenceType = null,
        public ?int $referenceId = null,

        public ?int $performedBy = null,
        public ?string $notes = null,
    ) {}

    public function isInflow(): bool
    {
        return $this->quantityDelta > 0;
    }

    /** Positive magnitude, regardless of direction. */
    public function magnitude(): int
    {
        return abs($this->quantityDelta);
    }
}
