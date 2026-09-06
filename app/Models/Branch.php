<?php
// app/Models/Branch.php

namespace App\Models;

use App\Models\Appointment;
use App\Models\Inventory;
use App\Models\User;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{HasMany, BelongsToMany};
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'branch_code',
        'address',
        'city',
        'province',
        'phone',
        'email',
        'is_active',
        'opening_hours',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ═══════════════════════════════════════════════════════
    // ✅ RELATIONSHIPS
    // ═══════════════════════════════════════════════════════

    /**
     * Users assigned to this branch via pivot (many-to-many)
     * Doctor/receptionist can work at multiple branches
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_user')
            ->withTimestamps();
    }

    /**
     * Users where THIS is their PRIMARY branch (via users.branch_id)
     */
    public function primaryUsers(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    /**
     * All doctors working at this branch
     * (Uses the many-to-many via branch_user pivot)
     */
    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_user')
            ->whereHas('roles', function ($q) {
                $q->where('name', 'doctor');
            })
            ->withTimestamps();
    }

    /**
     * Inventory at this branch
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * Appointments at this branch
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    // ═══════════════════════════════════════════════════════
    // ✅ ACCESSORS
    // ═══════════════════════════════════════════════════════

    /**
     * Full formatted address
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->province,
        ]);
        return implode(', ', $parts);
    }

    /**
     * Display name with code
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->branch_code
            ? "{$this->name} ({$this->branch_code})"
            : $this->name;
    }

    // ═══════════════════════════════════════════════════════
    // ✅ SCOPES
    // ═══════════════════════════════════════════════════════

    /**
     * Scope: Only active branches
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Search by name, code, city, or province
     */
    public function scopeSearch($query, ?string $search)
    {
        if (empty($search)) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('branch_code', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhere('province', 'like', "%{$search}%");
        });
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_user')
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', [
                    'dentist',
                    'doctor',
                    'admin',
                    'super-admin',
                    'superadmin',
                    'receptionist',
                    'staff',
                    'assistant',
                ]);
            })
            ->withTimestamps();
    }
}
