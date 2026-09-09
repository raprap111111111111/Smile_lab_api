<?php

namespace App\Domain\Inventories\Services;

use App\Domain\Inventories\DTOs\MovementResult;
use App\Domain\Inventories\DTOs\RecordMovementDTO;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * The one place stock changes.
 *
 * Everything — deliveries, manual usage, corrections, transfers, automatic
 * deduction from completed appointments — comes through record(). That is what
 * keeps the ledger and the `inventories` aggregate from ever disagreeing:
 * quantity is not stored and edited, it is recomputed from the movements each
 * time, inside the same transaction that wrote them.
 *
 * Nothing outside this class may write `inventories.quantity`,
 * `inventory_batches.quantity_remaining`, or `stock_movements`.
 */
final class StockLedger
{
    public function __construct(
        private readonly FefoAllocator $allocator,
    ) {}

    /**
     * Apply one movement request.
     *
     * Safe to nest inside a caller's transaction — Laravel turns the inner one
     * into a savepoint — which is how TransferStockAction gets both halves of a
     * branch-to-branch move to commit or roll back together.
     */
    public function record(RecordMovementDTO $dto): MovementResult
    {
        return DB::transaction(function () use ($dto): MovementResult {
            if ($dto->quantityDelta === 0) {
                // A correction that changes nothing is not an event. Recording
                // it would pad the ledger with rows that explain nothing.
                return MovementResult::empty(
                    $this->currentBalance($dto->branchId, $dto->itemId)
                );
            }

            return $dto->isInflow()
                ? $this->applyInflow($dto)
                : $this->applyOutflow($dto);
        });
    }

    // ── Directions ────────────────────────────────────

    /**
     * Stock arriving. Always opens a new batch, even for a positive adjustment:
     * units that exist have to sit in a lot with an expiry, or FEFO cannot see
     * them and they can never be drawn.
     */
    private function applyInflow(RecordMovementDTO $dto): MovementResult
    {
        $batch = InventoryBatch::create([
            'branch_id'          => $dto->branchId,
            'item_id'            => $dto->itemId,
            'supplier_id'        => $dto->supplierId,
            'lot_number'         => $dto->lotNumber,
            'expiry_date'        => $dto->expiryDate,
            'quantity_received'  => $dto->quantityDelta,
            'quantity_remaining' => $dto->quantityDelta,
            'received_at'        => $dto->receivedAt ?? now()->toDateString(),
            'notes'              => $dto->notes,
        ]);

        $balance = $this->currentBalance($dto->branchId, $dto->itemId) + $dto->quantityDelta;

        $movement = $this->writeMovement($dto, $dto->quantityDelta, $balance, $batch->id);

        $this->syncAggregate($dto->branchId, $dto->itemId, $balance);

        return new MovementResult([$movement], 0, $balance);
    }

    /**
     * Stock leaving. Draws across batches earliest-expiry-first, writing one
     * ledger row per batch touched so the trail says which lot was used.
     *
     * A shortfall is recorded, never rejected — see the class note on
     * ConsumeAppointmentSuppliesAction's contract that clinical work is never
     * blocked by a supply-cupboard number.
     */
    private function applyOutflow(RecordMovementDTO $dto): MovementResult
    {
        [
            'allocations' => $allocations,
            'shortfall'   => $shortfall,
        ] = $this->allocator->allocate($dto->branchId, $dto->itemId, $dto->magnitude());

        $balance = $this->currentBalance($dto->branchId, $dto->itemId);
        $movements = [];

        foreach ($allocations as $allocation) {
            /** @var InventoryBatch $batch */
            $batch = $allocation['batch'];
            $take  = $allocation['quantity'];

            $batch->decrement('quantity_remaining', $take);

            $balance -= $take;
            $movements[] = $this->writeMovement($dto, -$take, $balance, $batch->id);
        }

        if ($shortfall > 0) {
            $balance -= $shortfall;

            // No batch: there was nothing physical behind these units. This is
            // the row that drives the branch total negative and makes the gap
            // visible instead of silently clamping at zero.
            $movements[] = $this->writeMovement(
                $dto,
                -$shortfall,
                $balance,
                null,
                $dto->reason ?? 'Recorded beyond available stock.',
            );
        }

        $this->syncAggregate($dto->branchId, $dto->itemId, $balance);

        return new MovementResult($movements, $shortfall, $balance);
    }

    // ── Plumbing ──────────────────────────────────────

    private function writeMovement(
        RecordMovementDTO $dto,
        int $delta,
        int $balanceAfter,
        ?int $batchId,
        ?string $reasonOverride = null,
    ): StockMovement {
        return StockMovement::create([
            'branch_id'          => $dto->branchId,
            'item_id'            => $dto->itemId,
            'inventory_batch_id' => $batchId,
            'type'               => $dto->type,
            'quantity_delta'     => $delta,
            'balance_after'      => $balanceAfter,
            'reason'             => $reasonOverride ?? $dto->reason,
            'reference_type'     => $dto->referenceType,
            'reference_id'       => $dto->referenceId,
            'performed_by'       => $dto->performedBy,
            'notes'              => $dto->notes,
        ]);
    }

    /**
     * Quantity on hand IS the sum of the ledger. Deriving it rather than
     * tracking it separately means the two can never drift.
     */
    private function currentBalance(int $branchId, int $itemId): int
    {
        return (int) StockMovement::query()
            ->forStock($branchId, $itemId)
            ->sum('quantity_delta');
    }

    /**
     * Refresh the `inventories` summary row the existing API and client read.
     *
     * `expiry_date` there now means "the earliest expiry still on the shelf",
     * which keeps the long-standing `is_expired` flag meaningful once a single
     * item can hold several lots.
     */
    private function syncAggregate(int $branchId, int $itemId, int $balance): void
    {
        $earliestExpiry = InventoryBatch::query()
            ->where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->open()
            ->whereNotNull('expiry_date')
            ->min('expiry_date');

        Inventory::updateOrCreate(
            ['branch_id' => $branchId, 'item_id' => $itemId],
            ['quantity' => $balance, 'expiry_date' => $earliestExpiry],
        );
    }
}
