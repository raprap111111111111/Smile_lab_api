<?php

namespace App\Domain\Role\Actions;

use App\Domain\Role\DTOs\UpdateRoleDTO;
use App\Domain\Role\Repositories\RoleRepository;
use App\Models\Role;

class UpdateRoleAction
{
    public function __construct(
        private readonly RoleRepository $repository
    ) {}

    // app/Domain/Role/Actions/UpdateRoleAction.php

    public function execute(Role $role, UpdateRoleDTO $dto): Role
    {
        $updateData = array_filter([
            'name'        => $dto->name,
            'description' => $dto->description,
            'is_active'   => $dto->isActive,
        ], function ($value) {
            // array_filter removes nulls. 
            // Note: If you want to allow setting is_active to 'false', 
            // use a more specific check.
            return $value !== null;
        });

        $role->update($updateData);
        return $role;
    }
}
