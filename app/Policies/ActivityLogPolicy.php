<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('activity-log.viewAny');
    }

    public function view(User $user, ActivityLog $log): bool
    {
        return $user->can('activity-log.view');
    }

    /**
     * Activity logs are auto-generated; no one creates manually.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Activity logs are immutable — never editable.
     */
    public function update(User $user, ActivityLog $log): bool
    {
        return false;
    }

    /**
     * Only super-admin can delete logs (for compliance/audit purposes).
     */
    public function delete(User $user, ActivityLog $log): bool
    {
        return $user->hasAnyRole(['super-admin', 'superadmin']);
    }
}