<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{HasOne, HasMany, BelongsToMany, BelongsTo};
use App\Notifications\Contracts\RoutesAppointmentMail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles, HasFactory, HasApiTokens, Notifiable, LogsActivity;

    // ═══════════════════════════════════════════════════════
    // ✅ FILLABLE — All fields that can be mass-assigned
    // ═══════════════════════════════════════════════════════
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'profile_photo',
        'branch_id',           // ✅ ADD
        'email_verified_at',   // ✅ ADD
    ];

    // ═══════════════════════════════════════════════════════
    // ✅ HIDDEN — Fields never exposed in JSON responses
    // ═══════════════════════════════════════════════════════
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // ═══════════════════════════════════════════════════════
    // ✅ APPENDS — Auto-include these virtual attributes
    // ═══════════════════════════════════════════════════════
    protected $appends = [
        'profile_photo_url',   // ✅ Always available in JSON
    ];

    // ═══════════════════════════════════════════════════════
    // ✅ CASTS — Auto-convert types
    // ═══════════════════════════════════════════════════════
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ═══════════════════════════════════════════════════════
    // ✅ RELATIONSHIPS
    // ═══════════════════════════════════════════════════════

    /**
     * Branches the user works at (many-to-many)
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_user')
            ->withTimestamps();
    }

    /**
     * Primary branch (if branch_id is set)
     */
    public function primaryBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Doctor profile (if user is a doctor)
     */
    public function doctorProfile(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    /**
     * Patient profile (if user is a patient)
     */
    public function patientProfile(): HasOne
    {
        return $this->hasOne(PatientProfile::class);
    }

    /**
     * Appointments as a patient
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Appointments booked BY this user (as receptionist/admin)
     */
    public function bookedAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'created_by');
    }

    /**
     * Social login accounts
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Payments recorded by this user (if receptionist)
     */
    public function recordedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'recorded_by');
    }

    // ═══════════════════════════════════════════════════════
    // ✅ ROLE HELPER METHODS
    // ═══════════════════════════════════════════════════════

    public function isSuperAdmin(): bool
    {
        return $this->hasAnyRole(['super-admin', 'superadmin']);
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['admin', 'super-admin', 'superadmin']);
    }

    public function isDoctor(): bool
    {
        return $this->hasAnyRole(['doctor', 'dentist']);
    }

    public function isReceptionist(): bool
    {
        return $this->hasAnyRole(['receptionist', 'staff']);
    }

    public function isAssistant(): bool
    {
        return $this->hasAnyRole(['assistant', 'staff']);
    }

    public function isPatient(): bool
    {
        return $this->hasRole('patient');
    }

    /**
     * Check if user has any staff role
     */
    public function isStaff(): bool
    {
        return $this->hasAnyRole([
            'super-admin',
            'superadmin',
            'admin',
            'doctor',
            'dentist',
            'staff',
            'receptionist',
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // ✅ SPATIE PERMISSION CONFIG
    // ═══════════════════════════════════════════════════════
    public function getDefaultGuardName(): string
    {
        return 'api';
    }

    // ═══════════════════════════════════════════════════════
    // ✅ ACCESSORS
    // ═══════════════════════════════════════════════════════

    /**
     * Full URL for profile photo — served via CORS-enabled route
     */
  // ═══════════════════════════════════════════════════════
// ✅ ACCESSORS
// ═══════════════════════════════════════════════════════

    /**
     * Full URL for profile photo (CORS-safe via custom route)
     */
    public function getProfilePhotoUrlAttribute(): ?string
    {
        // Null (not a placeholder URL) so clients fall back to initials
        // instead of requesting an image that does not exist.
        if (!$this->profile_photo) {
            return null;
        }

        // profile_photo stored as: "profile_photos/xyz.jpg"
        // We extract filename for the CORS route
        $filename = basename($this->profile_photo);

        return url('/profile-photo/' . $filename);
    }

    /**
     * Alternative: direct storage URL (no CORS, faster)
     */
    public function getProfilePhotoStorageUrlAttribute(): ?string
    {
        return $this->profile_photo
            ? Storage::disk('public')->url($this->profile_photo)
            : null;
    }
    /**
     * Get user's initials (for avatar placeholder)
     */
    public function getInitialsAttribute(): string
    {
        $parts = explode(' ', trim($this->name));
        $initials = '';

        foreach ($parts as $part) {
            if (!empty($part)) {
                $initials .= strtoupper($part[0]);
                if (strlen($initials) >= 2) break;
            }
        }

        return $initials ?: '?';
    }

    /**
     * Check if user's email is verified
     */
    public function getIsVerifiedAttribute(): bool
    {
        return $this->email_verified_at !== null;
    }

    // ═══════════════════════════════════════════════════════
    // ✅ SCOPES (query helpers)
    // ═══════════════════════════════════════════════════════

    /**
     * Scope: Only verified users
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Scope: Users in a specific branch
     */
    public function scopeInBranch($query, int $branchId)
    {
        return $query->whereHas('branches', function ($q) use ($branchId) {
            $q->where('branches.id', $branchId);
        });
    }

    /**
     * Scope: Search by name, email, or phone
     */
    public function scopeSearch($query, ?string $search)
    {
        if (empty($search)) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });
    }

    // ═══════════════════════════════════════════════════════
// ✅ RELATIONSHIPS
// ═══════════════════════════════════════════════════════

// ... your existing relationships (branches, appointments, etc.)

    /**
     * Patient attachments (X-rays, photos, documents)
     */
    public function patientAttachments(): HasMany
    {
        return $this->hasMany(PatientAttachment::class);
    }

    /**
     * Only X-ray attachments
     */
    public function xrays(): HasMany
    {
        return $this->hasMany(PatientAttachment::class)->where('is_xray', true);
    }

    /**
     * Only completed AI scans
     */
    public function scannedXrays(): HasMany
    {
        return $this->hasMany(PatientAttachment::class)
            ->where('is_xray', true)
            ->where('scan_status', 'completed');
    }

    /**
     * Where to email this user for a given notification.
     *
     * MailMessage has no ->to(); MailChannel resolves the recipient only from
     * here. Appointment notifications implement RoutesAppointmentMail so a
     * booking made for a spouse or child reaches the person attending rather
     * than the account holder.
     *
     * Returning an empty array makes MailChannel skip the send instead of
     * throwing.
     *
     * @return array<string, string>|string
     */
    public function routeNotificationForMail(Notification $notification): array|string
    {
        if ($notification instanceof RoutesAppointmentMail) {
            $recipient = $notification->appointmentMailRecipient($this);

            if ($recipient !== []) {
                return $recipient;
            }
        }

        return [$this->email => (string) $this->name];
    }
}
