<?php

namespace App\Domain\Items\Actions;

use App\Domain\Items\DTOs\UpdateItemDTO;
use App\Domain\Items\Repositories\ItemRepository;
use App\Domain\Items\Services\ItemService;
use App\Models\Item;

class UpdateItemAction
{
    public function __construct(
        private readonly ItemRepository $repository,
        private readonly ItemService $service
    ) {}

    public function execute(Item $item, UpdateItemDTO $dto)
    {
        if ($dto->minimumThreshold !== null) {
            $this->service->validateThreshold($dto->minimumThreshold);
        }

        $sku = $dto->sku ? $this->service->formatSku($dto->sku) : null;

        $data = array_filter([
            'name' => $dto->name,
            'sku' => $sku,
            'category' => $dto->category,
            'unit_of_measure' => $dto->unitOfMeasure,
            'minimum_threshold' => $dto->minimumThreshold,
            'maximum_threshold' => $dto->maximumThreshold,
        ], fn($value) => !is_null($value));

        return $this->repository->update($item, $data);
    }
}
