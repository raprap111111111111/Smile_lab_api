<?php

namespace App\Domain\Inventories\Repositories;

use App\Domain\Branch\Services\BranchScope;
use App\Models\Inventory;
use App\Support\Query\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class InventoryRepository extends BaseRepository
{
    public function __construct(
        private readonly BranchScope $branchScope,
    ) {}

    protected string $model = Inventory::class;

    // InventoryResource emits `item` and `branch` unconditionally rather than
    // through whenLoaded(), so leaving this empty made index() lazy-load both
    // on every row.
    protected array $relations = [
        'item.supplier',
        'branch',
    ];

    // A stock row has nothing searchable of its own — the name and SKU a user
    // types live on the item. BaseQueryApplier::applySearch() understands dot
    // notation and turns these into orWhereHas().
    protected array $searchable = [
        'item.name',
        'item.sku',
        'item.category',
        'branch.name',
    ];

    protected array $filterable = [
        'branch_id',
        'item_id',
    ];

    protected array $sortable = [
        'id',
        'quantity',
        'expiry_date',
        'created_at',
    ];

    protected string $defaultOrderBy = 'created_at';
    protected string $defaultOrderDirection = 'desc';

    public function paginate(array $params = [], ?string $resourceClass = null): array
    {
        $query = $this->query();

        // Mandatory, not a caller-supplied filter. `branch_id` stays in
        // $filterable so a user can narrow WITHIN their branches, but they can
        // never widen past them — an id outside the allowlist simply matches
        // nothing rather than leaking another branch's stock.
        if ($user = Auth::user()) {
            $this->branchScope->apply($query, $user);
        }

        if (filter_var($params['low_stock_only'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->applyLowStockFilter($query);
        }

        return $this->paginateQuery($query, $params, $resourceClass);
    }

    /**
     * Low stock compares two tables — this row's quantity against the item's own
     * reorder point — so it cannot be expressed as a declarative filter. Mirrors
     * Inventory::isLowStock().
     */
    protected function applyLowStockFilter(Builder $query): void
    {
        $query->whereHas('item', function (Builder $itemQuery): void {
            $itemQuery->whereColumn('items.minimum_threshold', '>=', 'inventories.quantity');
        });
    }

    /**
     * Check if a specific item is already registered in a branch's inventory
     */
    public function hasExistingRecord(int $branchId, int $itemId, ?int $excludeId = null): bool
    {
        $query = ($this->model)::query()
            ->where('branch_id', $branchId)
            ->where('item_id', $itemId);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
