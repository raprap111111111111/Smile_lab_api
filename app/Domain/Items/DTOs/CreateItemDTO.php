<?php

namespace App\Domain\Items\DTOs;

final readonly class CreateItemDTO
{
    public function __construct(
        public string $name,
        public string $sku,
        public string $category,
        public string $unitOfMeasure,
        public int $minimumThreshold = 10,
        public ?int $maximumThreshold = null,
    ) {}
}
