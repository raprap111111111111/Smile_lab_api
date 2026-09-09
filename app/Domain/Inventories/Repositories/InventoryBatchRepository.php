<?php

declare(strict_types=1);

namespace App\Domain\Inventories\Repositories;

use App\Domain\Branch\Services\BranchScope;
use App\Models\InventoryBatch;
use App\Support\Query\BaseRepository;
use Illuminate\Support\Facades\Auth;

class InventoryBatchRepository extends BaseRepository
{
    public function __construct(
        private readonly BranchScope $branchScope,
    ) {}

    protected string $model = InventoryBatch::class;

    protected array $relations = [
        'item',
        'supplier',
        'branch',
    ];

    protected array $searchable = [
        'lot_number',
        'item.name',
        'item.sku',
    ];

    protected array $filterable = [
        'branch_id'   => 'exact',
        'item_id'     => 'exact',
        'expiry_date' => 'date_range',
    ];

    protected array $sortable = [
        'id',
        'expiry_date',
        'received_at',
        'quantity_remaining',
    ];

    // Earliest expiry first — the same order consumption draws in, so the list
    // reads as "what goes next".
    protected string $defaultOrderBy = 'expiry_date';
    protected string $defaultOrderDirection = 'asc';

    public function paginate(array $params = [], ?string $resourceClass = null): array
    {
        $query = $this->query();

        if ($user = Auth::user()) {
            $this->branchScope->apply($query, $user);
        }

        // Depleted batches are history; the ledger is where you go for that.
        if (filter_var($params['open_only'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            $query->open();
        }

        return $this->paginateQuery($query, $params, $resourceClass);
    }
}
