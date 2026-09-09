<?php

declare(strict_types=1);

namespace App\Domain\Suppliers\Repositories;

use App\Models\Supplier;
use App\Support\Query\BaseRepository;

class SupplierRepository extends BaseRepository
{
    protected string $model = Supplier::class;

    protected array $searchable = [
        'name',
        'contact_person',
        'email',
        'phone',
    ];

    protected array $filterable = [
        'is_active',
    ];

    protected array $sortable = [
        'id',
        'name',
        'created_at',
    ];

    protected string $defaultOrderBy = 'name';
    protected string $defaultOrderDirection = 'asc';
}
