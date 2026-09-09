<?php

declare(strict_types=1);

namespace App\Http\Controllers\v1;

use App\Domain\Suppliers\Actions\CreateSupplierAction;
use App\Domain\Suppliers\Actions\DeleteSupplierAction;
use App\Domain\Suppliers\Actions\UpdateSupplierAction;
use App\Domain\Suppliers\Mappers\SupplierMapper;
use App\Domain\Suppliers\Repositories\SupplierRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\v1\Supplier\DeleteSupplierRequest;
use App\Http\Requests\v1\Supplier\GetAllSuppliersRequest;
use App\Http\Requests\v1\Supplier\StoreSupplierRequest;
use App\Http\Requests\v1\Supplier\UpdateSupplierRequest;
use App\Http\Resources\v1\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierRepository $repository,
        private readonly CreateSupplierAction $createAction,
        private readonly UpdateSupplierAction $updateAction,
        private readonly DeleteSupplierAction $deleteAction,
    ) {}

    public function index(GetAllSuppliersRequest $request): JsonResponse
    {
        $result = $this->repository->paginate($request->validated(), SupplierResource::class);
        return $this->successResponse($result, 'Suppliers retrieved successfully.');
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return $this->successResponse(
            new SupplierResource($supplier->loadCount('items')),
            'Supplier loaded successfully.'
        );
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $dto = SupplierMapper::fromRequest($request);
        $supplier = $this->createAction->execute($dto);

        return $this->successResponse(
            new SupplierResource($supplier),
            'Supplier created successfully.',
            JsonResponse::HTTP_CREATED
        );
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $dto = SupplierMapper::fromRequest($request);
        $updated = $this->updateAction->execute($supplier, $dto);

        return $this->successResponse(
            new SupplierResource($updated),
            'Supplier updated successfully.'
        );
    }

    public function destroy(DeleteSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $this->deleteAction->execute($supplier);
        return $this->successResponse(null, 'Supplier deleted successfully.');
    }
}
