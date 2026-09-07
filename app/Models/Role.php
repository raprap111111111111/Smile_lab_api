<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    // Define the custom fields so they can be saved
    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'is_active',
    ];

    // Optional: Casts for your custom fields
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? (is_numeric($value) ? 'id' : 'name'), $value)->firstOrFail();
    }

    protected static function booted()
    {
        // Whenever a role is being created, force the guard to 'api'
        static::creating(function ($role) {
            $role->guard_name = 'api';
        });

        // Whenever a role is being saved (update or create), force the guard to 'api'
        static::saving(function ($role) {
            $role->guard_name = 'api';
        });
    }
}