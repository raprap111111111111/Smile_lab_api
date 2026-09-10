<?php

namespace App\Http\Controllers\v1;

use App\Domain\User\Actions\CreateUserAction;
use App\Domain\User\Actions\DeleteUserAction;
use App\Domain\User\Actions\UpdateUserAction;
use App\Domain\User\Mappers\UserMapper;
use App\Domain\User\Repositories\UserRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\v1\User\GetAllUserRequest;
use App\Http\Requests\v1\User\StoreUserRequest;
use App\Http\Requests\v1\User\UpdateUserRequest;
use App\Http\Resources\v1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly CreateUserAction $createAction,
        private readonly UpdateUserAction $updateAction,
        private readonly DeleteUserAction $deleteAction
    ) {}

    public function index(GetAllUserRequest $request): JsonResponse
    {
        $result = $this->repository->paginate($request->validated(), UserResource::class);
        return $this->responseSuccess($result, 'Users retrieved successfully');
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return $this->responseSuccess(
            new UserResource($user),
            'User found successfully'
        );
    }

    public function me(): JsonResponse
    {
        $authUser = Auth::user();

        $user = User::with(['patientProfile', 'branches'])
            ->findOrFail($authUser->id);

        return $this->responseSuccess(
            new UserResource($user),
            'User found successfully'
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->createAction->execute(
            UserMapper::fromCreateRequest($request)
        );

        return $this->responseSuccess(
            new UserResource($user),
            'User created successfully',
            JsonResponse::HTTP_CREATED
        );
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $updatedUser = $this->updateAction->execute(
            $user,
            UserMapper::fromUpdateRequest($request)
        );

        return $this->responseSuccess(
            new UserResource($updatedUser),
            'User updated successfully'
        );
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $actor = $request->user();

        if ($actor !== null && $actor->id === $user->id) {
            abort(422, 'You cannot delete your own account.');
        }

        if ($user->isSuperAdmin() && ($actor === null || ! $actor->isSuperAdmin())) {
            abort(403, 'Only Super Administrators can delete Super Administrator accounts.');
        }

        $this->deleteAction->execute($user);
        return $this->responseSuccess(null, 'User deleted successfully');
    }
}
