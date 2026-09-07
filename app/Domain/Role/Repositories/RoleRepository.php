<?php

namespace App\Domain\Role\Repositories;

use App\Models\Role;
use App\Support\Query\BaseRepository;

class RoleRepository extends BaseRepository
{
    protected string $model = Role::class;

    protected array $searchable = ['name', 'description'];
    protected array $filterable = ['is_active'];
    protected array $sortable   = ['name', 'created_at'];

    protected array $with      = ['permissions'];
    protected array $withCount = ['users', 'permissions'];

    protected string $defaultOrderBy        = 'created_at';
    protected string $defaultOrderDirection = 'desc';
}