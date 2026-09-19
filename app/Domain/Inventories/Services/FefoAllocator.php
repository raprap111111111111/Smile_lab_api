<?php

namespace App\Domain\Inventories\Services;

use App\Models\InventoryBatch;

/**
 * Decides which batches a withdrawal draws from: first-expired, first-out.
 *
 * Pure allocation — it reads and reserves, but writes nothing. StockLedger owns
 * the mutation. Splitting it this way keeps the ordering rule testable on its
 * own, which matters because getting FEFO subtly wrong wastes stock silently.
 */
final class FefoAllocator
{
    /**
     * Reserve `$quantity` units across the branch's open batches.
     *
     * MUST be called inside a transaction: the row lock below is what stops two
     * simultaneous appointment completions from both drawing the last carpule.
     * Without a surrounding transaction the lock is released immediately and the
     * pair can double-spend.
     *
     * Returns the unsatisfiable remainder rather than throwing. Running out of
     * gloves is a fact to record, not an error to raise — the caller decides
     * what it means, and for clinical consumption it must never block.
     *
     * When `$batchId` is given, only that batch is drawn from; FEFO order is bypassed.
     *
     * @return array{allocations: list<array{batch: InventoryBatch, quantity: int}>, shortfall: int}
     */
    public function allocate(int $branchId, int $itemId, int $quantity, ?int $batchId = null): array
    {
        if ($quantity <= 0) {
            return ['allocations' => [], 'shortfall' => 0];
        }

        $batches = InventoryBatch::query()
            ->where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->when($batchId !== null, fn ($query) => $query->whereKey($batchId))
            ->open()
            ->fefo()
            ->lockForUpdate()
            ->get();

        $remaining = $quantity;
        $allocations = [];

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, $batch->quantity_remaining);

            $allocations[] = ['batch' => $batch, 'quantity' => $take];
            $remaining -= $take;
        }

        return ['allocations' => $allocations, 'shortfall' => $remaining];
    }
}
