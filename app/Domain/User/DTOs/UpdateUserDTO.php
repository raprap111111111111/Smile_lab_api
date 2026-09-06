<?php

namespace App\Domain\User\DTOs;

use Illuminate\Http\UploadedFile;

final readonly class UpdateUserDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?array $branchIds = null,
        public ?string $password = null,
        public ?bool $isActive = null,
        public readonly ?UploadedFile $photo = null, // ✅ NEW
        public ?string $role = null,
    ) {}
}