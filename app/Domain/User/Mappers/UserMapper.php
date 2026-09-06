<?php

namespace App\Domain\User\Mappers;

use App\Domain\User\DTOs\CreateUserDTO;
use App\Domain\User\DTOs\UpdateUserDTO;
use App\Http\Requests\v1\User\StoreUserRequest;
use App\Http\Requests\v1\User\UpdateUserRequest;

class UserMapper
{
    public static function fromCreateRequest(StoreUserRequest $request): CreateUserDTO
    {
        return new CreateUserDTO(
            name: $request->validated('name'),
            email: $request->validated('email'),
            role: $request->validated('role'),
            phone: $request->validated('phone'),
            branchId: $request->validated('branch_id'),
            password: $request->validated('password'),
            isActive: (bool) $request->validated('is_active', true),
        );
    }

    public static function fromUpdateRequest(UpdateUserRequest $request): UpdateUserDTO
    {
        return new UpdateUserDTO(
            name: $request->validated('name'),
            email: $request->validated('email'),
            phone: $request->validated('phone'),
            branchIds: $request->input('branch_ids'),
            password: $request->validated('password'),
            isActive: $request->validated('is_active'),
            photo:     $request->file('photo'), // ✅ NEW
            role:      $request->validated('role'),
        );
    }
}