<?php

namespace App\Http\Controllers\v1;

use App\Domain\Inventories\Actions\AdjustStockAction;
use App\Domain\Inventories\Actions\RecordUsageAction;
use App\Domain\Inventories\Actions\StockInAction;
use App\Domain\Inventories\Actions\TransferStockAction;
use App\Domain\Inventories\Actions\WriteOffStockAction;
use App\Domain\Inventories\DTOs\MovementResult;
use App\Domain\Inventories\Mappers\StockMapper;
use App\Http\Controllers\Controller;
use App\Http\Requests\v1\Inventory\AdjustStockRequest;
use App\Http\Requests\v1\Inventory\RecordUsageRequest;
use App\Http\Requests\v1\Inventory\StockInRequest;
use App\Http\Requests\v1\Inventory\TransferStockRequest;
use App\Http\Resources\v1\InventoryResource;
use App\Http\Resources\v1\StockMovementResource;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stock movements, as opposed to the branch/item mapping InventoryController
 * maintains. These are the four verbs the `inventory.stock-in`, `.stock-out`,
 * `.adjust` and `.transfer` permissions were seeded for.
 *
 * Every response returns the refreshed summary row alongside the movements, so
 * the client can patch its list in place rather than refetching the page.
 */
class StockController extends Controller
{
    public function __construct(
        private readonly StockInAction $stockInAction,
        private readonly RecordUsageAction $recordUsageAction,
        private readonly AdjustStockAction $adjustStockAction,
        private readonly TransferStockAction $transferStockAction,
        private readonly WriteOffStockAction $writeOffStockAction,
    ) {}

    public function stockIn(StockInRequest $request): JsonResponse
    {
        try {
            $dto = StockMapper::fromStockInRequest($request);
            $result = $this->stockInAction->execute($dto);

            return $this->successResponse(
                $this->payload($dto->branchId, $dto->itemId, $result),
                'Stock received.',
                JsonResponse::HTTP_CREATED,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function usage(RecordUsageRequest $request): JsonResponse
    {
        try {
            $dto = StockMapper::fromUsageRequest($request);
            $result = $this->recordUsageAction->execute($dto);

            // A shortfall is reported, not refused — the supplies are already
            // gone, and rejecting the entry would only make the ledger wrong.
            $message = $result->hasShortfall()
                ? "Usage recorded, but stock was short by {$result->shortfall}. The balance is now negative."
                : 'Usage recorded.';

            return $this->successResponse(
                $this->payload($dto->branchId, $dto->itemId, $result),
                $message,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function adjust(AdjustStockRequest $request): JsonResponse
    {
        try {
            $dto = StockMapper::fromAdjustRequest($request);
            $result = $this->adjustStockAction->execute($dto);

            return $this->successResponse(
                $this->payload($dto->branchId, $dto->itemId, $result),
                $result->movements === []
                    ? 'Count matched the ledger — nothing to adjust.'
                    : 'Stock adjusted.',
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function transfer(TransferStockRequest $request): JsonResponse
    {
        try {
            $dto = StockMapper::fromTransferRequest($request);
            $result = $this->transferStockAction->execute($dto);

            return $this->successResponse([
                'quantity' => $result->quantity,
                'source' => [
                    'branch_id' => $dto->fromBranchId,
                    'balance_after' => $result->sourceBalance,
                    'inventory' => $this->inventoryResource($dto->fromBranchId, $dto->itemId),
                ],
                'destination' => [
                    'branch_id' => $dto->toBranchId,
                    'balance_after' => $result->destinationBalance,
                    'inventory' => $this->inventoryResource($dto->toBranchId, $dto->itemId),
                ],
                'movements' => StockMovementResource::collection($result->outbound->movements),
            ], 'Stock transferred.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            // Not enough stock to send. Unlike usage, a transfer is a promise
            // about the future, so this one really is a conflict.
            return $this->errorResponse($e->getMessage(), 409);
        }
    }

    public function writeoff(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'item_id'   => ['required', 'integer', 'exists:items,id'],
            'quantity'  => ['required', 'integer', 'min:1'],
            'reason'    => ['required', 'string', 'max:255'],
            'notes'     => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $branchId = (int) ($validated['branch_id'] ?? 1);
            $itemId = (int) $validated['item_id'];
            $quantity = (int) $validated['quantity'];
            $reason = (string) $validated['reason'];
            $notes = $validated['notes'] ?? null;
            $performedBy = $request->user()?->id;

            $result = $this->writeOffStockAction->execute(
                $branchId,
                $itemId,
                $quantity,
                $reason,
                $performedBy,
                $notes,
            );

            return $this->successResponse(
                $this->payload($branchId, $itemId, $result),
                'Stock written off successfully.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    // ── Helpers ───────────────────────────────────────

    private function payload(int $branchId, int $itemId, MovementResult $result): array
    {
        return [
            'inventory' => $this->inventoryResource($branchId, $itemId),
            'movements' => StockMovementResource::collection($result->movements),
            'shortfall' => $result->shortfall,
            'balance_after' => $result->balanceAfter,
        ];
    }

    private function inventoryResource(int $branchId, int $itemId): ?InventoryResource
    {
        $inventory = Inventory::with(['item', 'branch'])
            ->where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->first();

        return $inventory ? new InventoryResource($inventory) : null;
    }
}
