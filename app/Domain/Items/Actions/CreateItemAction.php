<?php

namespace App\Domain\Items\Actions;

use App\Domain\Items\DTOs\CreateItemDTO;
use App\Domain\Items\Repositories\ItemRepository;
use App\Domain\Items\Services\ItemService;

class CreateItemAction
{
    public function __construct(
        private readonly ItemRepository $repository,
        private readonly ItemService $service
    ) {}

    public function execute(CreateItemDTO $dto)
    {
        $this->service->validateThreshold($dto->minimumThreshold);
        $cleanSku = $this->service->formatSku($dto->sku);

        return $this->repository->create([
            'name' => $dto->name,
            'sku' => $cleanSku,
            'category' => $dto->category,
            'unit_of_measure' => $dto->unitOfMeasure,
            'supplier_id' => $dto->supplierId,
            'minimum_threshold' => $dto->minimumThreshold,
            'maximum_threshold' => $dto->maximumThreshold,
        ]);
    }
}
