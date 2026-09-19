<?php

namespace App\Domain\Inventories\Actions;

use App\Domain\Inventories\DTOs\MovementResult;
use App\Domain\Inventories\DTOs\RecordMovementDTO;
use App\Domain\Inventories\Services\StockLedger;
use App\Enums\StockMovementType;
use App\Models\InventoryBatch;
use Illuminate\Support\Facades\DB;

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
        ?string $notes = null,
        ?int $batchId = null,
    ): MovementResult {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Write-off quantity must be greater than zero.');
        }

        // The batch check and the ledger write share one transaction, and the
        // batch row is locked, so a concurrent draw cannot slip between them
        // and turn a batch write-off into a silent shortfall.
        return DB::transaction(function () use ($branchId, $itemId, $quantity, $reason, $performedBy, $notes, $batchId): MovementResult {
            if ($batchId !== null) {
                $this->assertBatchCanCover($batchId, $branchId, $itemId, $quantity);
            }

            return $this->ledger->record(new RecordMovementDTO(
                branchId: $branchId,
                itemId: $itemId,
                type: StockMovementType::EXPIRED_WRITEOFF,
                quantityDelta: -$quantity,
                reason: $reason,
                performedBy: $performedBy,
                notes: $notes,
                batchId: $batchId,
            ));
        });
    }

    private function assertBatchCanCover(int $batchId, int $branchId, int $itemId, int $quantity): void
    {
        $batch = InventoryBatch::query()->whereKey($batchId)->lockForUpdate()->first();

        if ($batch === null || (int) $batch->branch_id !== $branchId || (int) $batch->item_id !== $itemId) {
            throw new \InvalidArgumentException('That batch does not belong to this item at this branch.');
        }

        if ($quantity > $batch->quantity_remaining) {
            $lot = $batch->lot_number ?? "#{$batch->id}";

            throw new \InvalidArgumentException(
                "Batch {$lot} only has {$batch->quantity_remaining} left — cannot write off {$quantity}."
            );
        }
    }
}
