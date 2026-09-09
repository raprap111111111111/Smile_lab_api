<?php

namespace App\Domain\Inventories\Actions;

use App\Domain\Inventories\DTOs\MovementResult;
use App\Domain\Inventories\DTOs\RecordMovementDTO;
use App\Domain\Inventories\DTOs\StockInDTO;
use App\Domain\Inventories\Services\StockLedger;
use App\Enums\StockMovementType;

/** Records a delivery, opening a batch that FEFO can later draw from. */
class StockInAction
{
    public function __construct(
        private readonly StockLedger $ledger,
    ) {}

    public function execute(StockInDTO $dto): MovementResult
    {
        if ($dto->quantity <= 0) {
            throw new \InvalidArgumentException('Stock-in quantity must be greater than zero.');
        }

        return $this->ledger->record(new RecordMovementDTO(
            branchId: $dto->branchId,
            itemId: $dto->itemId,
            type: StockMovementType::STOCK_IN,
            quantityDelta: $dto->quantity,
            supplierId: $dto->supplierId,
            lotNumber: $dto->lotNumber,
            expiryDate: $dto->expiryDate,
            receivedAt: $dto->receivedAt,
            reason: $dto->reason,
            performedBy: $dto->performedBy,
            notes: $dto->notes,
        ));
    }
}
