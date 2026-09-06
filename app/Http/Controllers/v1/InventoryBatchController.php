<?php

declare(strict_types=1);

namespace App\Http\Controllers\v1;

use App\Domain\Inventories\Repositories\InventoryBatchRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\v1\Inventory\GetInventoryBatchesRequest;
use App\Http\Resources\v1\InventoryBatchResource;
use Illuminate\Http\JsonResponse;

/**
 * Read-only view of the lots behind a branch's stock.
 *
 * Batches are created and drawn down by StockLedger alone, so there is nothing
 * to write here — correcting a batch means recording a movement, which is what
 * keeps the ledger and the shelf in agreement.
 */
class InventoryBatchController extends Controller
{
    public function __construct(
        private readonly InventoryBatchRepository $repository,
    ) {}

    public function index(GetInventoryBatchesRequest $request): JsonResponse
    {
        $result = $this->repository->paginate(
            $request->validated(),
            InventoryBatchResource::class,
        );

        return $this->successResponse($result, 'Inventory batches retrieved.');
    }
}
