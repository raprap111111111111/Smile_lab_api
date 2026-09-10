<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user.viewAny');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('user.view') || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->can('user.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('user.update');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('user.delete');
    }

    public function impersonate(User $user, User $model): bool
    {
        return $user->can('user.impersonate');
    }

    public function resetPassword(User $user, User $model): bool
    {
        return $user->can('user.reset-password');
    }
}