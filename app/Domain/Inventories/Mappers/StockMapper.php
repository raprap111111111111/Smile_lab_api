<?php

namespace App\Domain\Inventories\Mappers;

use App\Domain\Inventories\DTOs\AdjustStockDTO;
use App\Domain\Inventories\DTOs\RecordUsageDTO;
use App\Domain\Inventories\DTOs\StockInDTO;
use App\Domain\Inventories\DTOs\TransferStockDTO;
use App\Http\Requests\v1\Inventory\AdjustStockRequest;
use App\Http\Requests\v1\Inventory\RecordUsageRequest;
use App\Http\Requests\v1\Inventory\StockInRequest;
use App\Http\Requests\v1\Inventory\TransferStockRequest;

class StockMapper
{
    public static function fromStockInRequest(StockInRequest $request): StockInDTO
    {
        return new StockInDTO(
            branchId: (int) $request->validated('branch_id'),
            itemId: (int) $request->validated('item_id'),
            quantity: (int) $request->validated('quantity'),
            supplierId: $request->filled('supplier_id') ? (int) $request->validated('supplier_id') : null,
            lotNumber: $request->validated('lot_number'),
            expiryDate: $request->validated('expiry_date'),
            receivedAt: $request->validated('received_at'),
            reason: $request->validated('reason'),
            notes: $request->validated('notes'),
            performedBy: $request->user()?->id,
        );
    }

    public static function fromUsageRequest(RecordUsageRequest $request): RecordUsageDTO
    {
        return new RecordUsageDTO(
            branchId: (int) $request->validated('branch_id'),
            itemId: (int) $request->validated('item_id'),
            quantity: (int) $request->validated('quantity'),
            reason: $request->validated('reason'),
            notes: $request->validated('notes'),
            performedBy: $request->user()?->id,
        );
    }

    public static function fromAdjustRequest(AdjustStockRequest $request): AdjustStockDTO
    {
        return new AdjustStockDTO(
            branchId: (int) $request->validated('branch_id'),
            itemId: (int) $request->validated('item_id'),
            countedQuantity: (int) $request->validated('counted_quantity'),
            reason: (string) $request->validated('reason'),
            lotNumber: $request->validated('lot_number'),
            expiryDate: $request->validated('expiry_date'),
            notes: $request->validated('notes'),
            performedBy: $request->user()?->id,
        );
    }

    public static function fromTransferRequest(TransferStockRequest $request): TransferStockDTO
    {
        return new TransferStockDTO(
            fromBranchId: (int) $request->validated('from_branch_id'),
            toBranchId: (int) $request->validated('to_branch_id'),
            itemId: (int) $request->validated('item_id'),
            quantity: (int) $request->validated('quantity'),
            reason: $request->validated('reason'),
            notes: $request->validated('notes'),
            performedBy: $request->user()?->id,
        );
    }
}
