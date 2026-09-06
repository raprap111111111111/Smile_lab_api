<?php

namespace App\Domain\Items\Actions;

use App\Domain\Inventories\DTOs\RecordMovementDTO;
use App\Domain\Inventories\Services\StockLedger;
use App\Domain\Items\Repositories\ItemRepository;
use App\Enums\StockMovementType;
use App\Models\Item;
use App\Models\TreatmentConsumable;
use Illuminate\Support\Facades\DB;

class DeleteItemAction
{
    public function __construct(
        private readonly ItemRepository $repository,
        private readonly StockLedger $stockLedger,
    ) {}

    /**
     * Archives a supply item: safely draws down on-hand stock and logs an
     * adjustment in the ledger, then soft-deletes the item while preserving
     * all movement history and batch audit records.
     */
    public function execute(Item $item): bool
    {
        return DB::transaction(function () use ($item) {
            $this->assertNotInTreatmentRecipes($item);

            // If there is on-hand stock, record an adjustment in the ledger to zero it out
            foreach ($item->inventories as $inventory) {
                if ($inventory->quantity > 0) {
                    $this->stockLedger->record(new RecordMovementDTO(
                        branchId: $inventory->branch_id,
                        itemId: $item->id,
                        type: StockMovementType::ADJUSTMENT,
                        quantityDelta: -$inventory->quantity,
                        reason: 'Item archived / discontinued',
                        notes: 'Automatic stock write-off upon supply item archival',
                        performedBy: auth()->id(),
                    ));
                }
            }

            return $item->delete();
        });
    }

    /**
     * An item that is still wired into a treatment procedure recipe cannot be
     * silently deleted, as doing so would alter future procedure deductions.
     *
     * @throws \RuntimeException
     */
    private function assertNotInTreatmentRecipes(Item $item): void
    {
        $hasRecipes = TreatmentConsumable::where('item_id', $item->id)->exists();

        if ($hasRecipes) {
            throw new \RuntimeException(
                'This item is registered as a consumable in one or more treatment procedures. '
                . 'Remove it from the treatment procedure recipes before deleting.'
            );
        }
    }
}
