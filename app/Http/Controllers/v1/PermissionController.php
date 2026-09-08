<?php

namespace App\Http\Controllers\v1;

use App\Domain\Permission\Actions\CreatePermissionAction;
use App\Domain\Permission\Actions\DeletePermissionAction;
use App\Domain\Permission\Actions\UpdatePermissionAction;
use App\Domain\Permission\Mappers\PermissionMapper;
use App\Domain\Permission\Repositories\PermissionRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\v1\Permission\GetAllPermissionRequest;
use App\Http\Requests\v1\Permission\StorePermissionRequest;
use App\Http\Requests\v1\Permission\UpdatePermissionRequest;
use App\Http\Resources\v1\PermissionResource;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionRepository $repository,
        private readonly CreatePermissionAction $createAction,
        private readonly UpdatePermissionAction $updateAction,
        private readonly DeletePermissionAction $deleteAction
    ) {}

    public function index(GetAllPermissionRequest $request): JsonResponse
    {
        $result = $this->repository->paginate($request->validated(), PermissionResource::class);

        return $this->responseSuccess($result, 'Permissions retrieved successfully');
    }

    public function grouped(): JsonResponse
    {
        $permissions = Permission::query()
            ->where('guard_name', 'api')
            ->orderBy('name')
            ->get(['id', 'name']);

        $grouped = $permissions
            ->groupBy(fn ($permission) => explode('.', $permission->name)[0])
            ->map(fn ($items) => $items->values());

        return $this->responseSuccess($grouped, 'Permissions retrieved successfully');
    }

    public function show(Permission $permission): JsonResponse
    {
        $this->authorize('view', $permission);

        return $this->responseSuccess(
            new PermissionResource($permission),
            'Permission found successfully'
        );
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {
        $permission = $this->createAction->execute(
            PermissionMapper::fromCreateRequest($request)
        );

        return $this->responseSuccess(
            new PermissionResource($permission),
            'Permission created successfully',
            JsonResponse::HTTP_CREATED
        );
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        $updatedPermission = $this->updateAction->execute(
            $permission,
            PermissionMapper::fromUpdateRequest($request)
        );

        return $this->responseSuccess(
            new PermissionResource($updatedPermission),
            'Permission updated successfully'
        );
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $this->authorize('delete', $permission);

        $this->deleteAction->execute($permission);

        return $this->responseSuccess(null, 'Permission deleted successfully');
    }
}