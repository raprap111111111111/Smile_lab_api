<?php

namespace App\Domain\Items\Mappers;

use App\Domain\Items\DTOs\CreateItemDTO;
use App\Domain\Items\DTOs\UpdateItemDTO;
use App\Http\Requests\v1\Item\StoreItemRequest;
use App\Http\Requests\v1\Item\UpdateItemRequest;

class ItemMapper
{
    public static function fromCreateRequest(StoreItemRequest $request): CreateItemDTO
    {
        return new CreateItemDTO(
            name: $request->validated('name'),
            sku: $request->validated('sku'),
            category: $request->validated('category'),
            unitOfMeasure: $request->validated('unit_of_measure'),
            minimumThreshold: (int) $request->validated('minimum_threshold', 10),
            maximumThreshold: $request->filled('maximum_threshold') ? (int) $request->validated('maximum_threshold') : null,
        );
    }

    public static function fromUpdateRequest(UpdateItemRequest $request): UpdateItemDTO
    {
        return new UpdateItemDTO(
            name: $request->validated('name'),
            sku: $request->validated('sku'),
            category: $request->validated('category'),
            unitOfMeasure: $request->validated('unit_of_measure'),
            minimumThreshold: $request->has('minimum_threshold') ? (int) $request->validated('minimum_threshold') : null,
            maximumThreshold: $request->has('maximum_threshold') ? ($request->filled('maximum_threshold') ? (int) $request->validated('maximum_threshold') : null) : null,
        );
    }
}
