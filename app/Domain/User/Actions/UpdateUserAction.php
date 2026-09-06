<?php
// app/Domain/User/Actions/UpdateUserAction.php

namespace App\Domain\User\Actions;

use App\Domain\User\DTOs\UpdateUserDTO;
use App\Domain\User\Repositories\UserRepository;
use App\Domain\User\Services\UserService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UpdateUserAction
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly UserService $userService
    ) {}

    public function execute(User $user, UpdateUserDTO $dto)
    {
        $data = [
            'name'      => $dto->name,
            'email'     => $dto->email,
            'phone'     => $dto->phone,
            'is_active' => $dto->isActive,
        ];

        // Hash password if provided
        if ($dto->password) {
            $data['password'] = $this->userService->hashPassword($dto->password);
        }

        // ✅ Handle photo upload
        if ($dto->photo instanceof UploadedFile) {
            $data['profile_photo'] = $this->uploadPhoto($user, $dto->photo);
        }

        // Remove null values
        $data = array_filter($data, fn($value) => !is_null($value));

        return DB::transaction(function () use ($user, $data, $dto) {
            $updatedUser = $this->repository->update($user, $data);

            if (isset($dto->branchIds)) {
                $updatedUser->branches()->sync($dto->branchIds);
            }

            if (!empty($dto->role)) {
                $updatedUser->syncRoles([$dto->role]);
            }

            return $updatedUser->load(['branches', 'patientProfile', 'roles']);
        });
    }

    /**
     * Upload photo to storage and delete old one
     */
    private function uploadPhoto(User $user, UploadedFile $photo): string
    {
        // Delete old photo if it's a stored file (not a default/URL)
        if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        // Web uploads arrive as raw bytes and may carry no client extension,
        // so fall back to the extension guessed from the file's own mime type.
        $extension = $photo->getClientOriginalExtension()
            ?: ($photo->guessExtension() ?: 'jpg');

        // Generate unique filename
        $filename = 'profile_' . $user->id . '_' . Str::random(10) . '.' . $extension;

        // Store in storage/app/public/profile_photos
        $path = $photo->storeAs('profile_photos', $filename, 'public');

        return $path;
    }
}