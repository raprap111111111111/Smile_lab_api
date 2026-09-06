<?php

namespace App\Domain\Inventories\Actions;

use App\Domain\Inventories\DTOs\MovementResult;
use App\Domain\Inventories\DTOs\RecordMovementDTO;
use App\Domain\Inventories\Services\StockLedger;
use App\Enums\StockMovementType;

/**
 * Handles disposal/write-off of expired, damaged, or recalled clinical inventory.
 */
class WriteOffStockAction
{
    public function __construct(
        private readonly StockLedger $ledger,
    ) {}

    public function execute(
        int $branchId,
        int $itemId,
        int $quantity,
        string $reason,
        ?int $performedBy = null,
        ?string $notes = null
    ): MovementResult {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Write-off quantity must be greater than zero.');
        }

        return $this->ledger->record(new RecordMovementDTO(
            branchId: $branchId,
            itemId: $itemId,
            type: StockMovementType::EXPIRED_WRITEOFF,
            quantityDelta: -$quantity,
            reason: $reason,
            performedBy: $performedBy,
            notes: $notes,
        ));
    }
}
