<?php

namespace App\Domain\Items\Repositories;

use App\Models\Item;
use App\Support\Query\BaseRepository;

class ItemRepository extends BaseRepository
{
    protected string $model = Item::class;

    protected array $relations = [
        'supplier',
    ];

    protected array $searchable = [
        'name',
        'sku',
        'category',
    ];

    protected array $filterable = [
        'category',
        'unit_of_measure',
    ];

    protected array $sortable = [
        'id',
        'name',
        'sku',
        'minimum_threshold',
        'created_at',
    ];

    protected string $defaultOrderBy = 'name';
    protected string $defaultOrderDirection = 'asc';

    public function paginate(array $params = [], ?string $resourceClass = null): array
    {
        $query = $this->model::query()->with($this->relations);

        if (($params['status'] ?? null) === 'archived' || !empty($params['archived'])) {
            $query = $this->model::onlyTrashed()->with($this->relations);
        } elseif (($params['status'] ?? null) === 'all') {
            $query = $this->model::withTrashed()->with($this->relations);
        }

        return $this->paginateQuery(
            $query,
            $params,
            $resourceClass
        );
    }
}
