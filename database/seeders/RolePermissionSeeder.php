<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $crud      = ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'];
        $readOnly  = ['viewAny', 'view'];
        $readWrite = ['viewAny', 'view', 'create', 'update'];
        $basicCrud = ['viewAny', 'view', 'create', 'update', 'delete'];

        $roles = [

            // ═══════════════════════════════════════════════════════════
            // 🔱 SUPER ADMIN — full system access
            // ═══════════════════════════════════════════════════════════
            'super-admin' => '*',
            'superadmin'  => '*',

            // ═══════════════════════════════════════════════════════════
            // 👑 ADMIN / OWNER
            // ═══════════════════════════════════════════════════════════
            'admin' => [

                // ── Dashboard & Settings ──────────────────────────────
                'dashboard'          => ['view'],
                'setting'            => ['view', 'update'],
                'activity-log'       => ['viewAny', 'view', 'export'],  // ✅ added export
                'notification'       => $basicCrud,

                // ── User Management ───────────────────────────────────
                'user'               => array_merge($basicCrud, ['reset-password']),
                'role'               => $readOnly,
                'permission'         => $readOnly,

                // ── Branch ────────────────────────────────────────────
                'branch'             => $basicCrud,

                // ── Doctors & Schedules ───────────────────────────────
                'doctor'             => $basicCrud,
                'doctor-schedule'    => $basicCrud,

                // ── Appointments ──────────────────────────────────────
                'appointment'        => array_merge($basicCrud, [
                    // Staff book on behalf of patients, so `create` alone is not
                    // enough: without this the appointment is silently saved
                    // against the staff member's own user_id.
                    'create-for-others',
                    'approve',
                    'reject',
                    'cancel',
                    'reschedule',
                    'check-in',
                    'check-out',
                    'no-show',
                    'complete',
                    'update-status',
                ]),
                'recall'             => array_merge($basicCrud, ['send-reminder', 'mark-completed']),

                // ── Patients ──────────────────────────────────────────
                'patient'            => array_merge($basicCrud, ['export', 'merge', 'transfer-branch']), // ✅ added merge
                'medical-profile'    => $basicCrud,
                'medical-item'       => $basicCrud,
                'medical-alert'      => $basicCrud,

                // ── Clinical ──────────────────────────────────────────
                'dental-chart'       => $basicCrud,
                'clinical-note'      => array_merge($readOnly, ['finalize']),
                'treatment'          => $basicCrud,
                'treatment-plan'     => array_merge($basicCrud, ['send-to-patient', 'mark-completed', 'record-consumables']),
                'prescription'       => array_merge($readOnly, ['print']),
                'attachment'         => array_merge($basicCrud, ['download', 'upload']),
                'consent-form'       => array_merge($basicCrud, ['send', 'void', 'print']),

                // ── Lab Cases ─────────────────────────────────────────
                'lab'                => $basicCrud,
                'lab-case'           => array_merge($basicCrud, [
                    'send',
                    'receive',
                    'quality-check',
                    'install',
                    'return',
                ]),

                // ── Financial ─────────────────────────────────────────
                'invoice'            => array_merge($basicCrud, [
                    'send',
                    'mark-paid',
                    'void',
                    'print',
                    'export',
                    'refund',
                ]),
                'payment'            => array_merge($basicCrud, [
                    'refund',
                    'void',
                    'print-receipt',
                    'export',
                ]),
                'discount'           => $basicCrud,
                'tax'                => $basicCrud,

                // ── Inventory ─────────────────────────────────────────
                'inventory'          => array_merge($basicCrud, [
                    'adjust',
                    'transfer',
                    'stock-in',
                    'stock-out',
                    'export',
                ]),
                'inventory-category' => $basicCrud,
                // PermissionSeeder creates item.* but nothing ever requested
                // them, so every role below super-admin 403'd on /api/v1/items —
                // the catalog behind the whole inventory feature.
                'item'               => $basicCrud,
                'supplier'           => $basicCrud,
                'purchase-order'     => array_merge($basicCrud, ['approve', 'receive', 'cancel']),

                // ── HR ────────────────────────────────────────────────
                'employee'           => array_merge($basicCrud, ['import', 'export']),
                'attendance'         => array_merge($basicCrud, ['check-in', 'check-out']),
                'leave-request'      => array_merge($basicCrud, [
                    'prepare',
                    'note',
                    'approve',
                    'receive',
                    'reject',
                ]),
                'payroll'            => array_merge($basicCrud, ['generate', 'approve', 'pay', 'export']),

                // ── Landing Page ──────────────────────────────────────
                'service'            => $basicCrud,
                'gallery'            => $basicCrud,
                'testimonial'        => array_merge($basicCrud, ['approve', 'reject']),
                'announcement'       => array_merge($basicCrud, ['publish', 'unpublish']),
                'faq'                => $basicCrud,

                // ── Reports ───────────────────────────────────────────
                'report'             => ['view', 'export'],
                'analytics'          => ['view'],
                'financial-report'   => ['view', 'export'],
                'clinical-report'    => ['view', 'export'],
                'inventory-report'   => ['view', 'export'],
                'patient-report'     => ['view', 'export'],

                // ── Communications ────────────────────────────────────
                'sms'                => ['send', 'view', 'configure'],
                'email'              => ['send', 'view', 'configure'],
                'reminder'           => array_merge($basicCrud, ['send']),

                // ── System ────────────────────────────────────────────
                'backup'             => ['create', 'restore', 'download', 'delete'], // ✅ added
            ],

            // ═══════════════════════════════════════════════════════════
            // 🩺 DENTIST — Clinical operations
            // ═══════════════════════════════════════════════════════════
            'dentist' => [

                // ── Dashboard ─────────────────────────────────────────
                'dashboard'        => ['view'],
                'notification'     => ['viewAny', 'view', 'update'],

                // ── User Management ───────────────────────────────────
                // Dentists may create staff accounts. `role` is read-only so
                // the create form can list assignable roles; StoreUserRequest
                // still blocks `patient` and reserves `super-admin` for
                // super-admins.
                'user'             => ['viewAny', 'view', 'create'],
                'role'             => $readOnly,

                // ── Schedule ──────────────────────────────────────────
                'doctor'           => $readOnly,
                'doctor-schedule'  => $readOnly,

                // ── Branch ────────────────────────────────────────────
                // Read-only: every schedule/inventory/appointment form has a
                // branch picker that calls GET /branches, which requires
                // `branch.viewAny`. Without it the dropdown 403s.
                'branch'           => $readOnly,

                // ── Appointments ──────────────────────────────────────
                'appointment'      => array_merge($readOnly, [
                    'update',
                    'check-in',
                    'check-out',
                    'complete',
                    'no-show',
                    'update-status',
                ]),

                // ── Patients ──────────────────────────────────────────
                'patient'          => $readOnly,
                'medical-profile'  => $readWrite,
                'medical-item'     => $readWrite,
                'medical-alert'    => $readWrite,

                // ── Clinical ──────────────────────────────────────────
                'dental-chart'     => $basicCrud,
                'clinical-note'    => array_merge($basicCrud, ['finalize', 'amend']),
                'treatment'        => $readWrite,
                'treatment-plan'   => array_merge($basicCrud, ['send-to-patient', 'mark-completed', 'record-consumables']),
                'prescription'     => array_merge($basicCrud, ['print', 'send']),
                'attachment'       => array_merge($basicCrud, ['download', 'upload']),
                'consent-form'     => ['viewAny', 'view', 'create', 'send'],

                // ── Lab Cases ─────────────────────────────────────────
                'lab-case'         => array_merge($readOnly, [
                    'create',
                    'send',
                    'receive',
                    'quality-check',
                    'install',
                ]),

                // ── Financial (read-only) ─────────────────────────────
                'invoice'          => $readOnly,
                'payment'          => $readOnly,

                // ── Inventory (read-only) ─────────────────────────────
                // `item` as well as `inventory`: the stock list renders the
                // item's name and SKU, so a dentist who cannot read the catalog
                // sees a list of blanks.
                'inventory'        => $readOnly,
                'item'             => $readOnly,

                // ── Landing Page ──────────────────────────────────────
                'service'          => $readOnly,
                'gallery'          => $readOnly,

                // ── HR (own records only) ─────────────────────────────
                'attendance'       => ['viewAny', 'view', 'check-in', 'check-out'],
                'leave-request'    => ['viewAny', 'view', 'create'],

                // ── Reports ───────────────────────────────────────────
                'clinical-report'  => ['view'],
            ],

            // ═══════════════════════════════════════════════════════════
            // 👔 RECEPTIONIST — Front desk operations
            // ═══════════════════════════════════════════════════════════
            'receptionist' => [

                // ── Dashboard ─────────────────────────────────────────
                'dashboard'        => ['view'],
                'notification'     => ['viewAny', 'view', 'update'],

                // ── Appointments ──────────────────────────────────────
                'appointment'      => array_merge($basicCrud, [
                    // Booking for walk-ins and phone calls is the front desk's
                    // core job — see the note on the admin role above.
                    'create-for-others',
                    'approve',
                    'reject',
                    'cancel',
                    'reschedule',
                    'check-in',
                    'check-out',
                    'no-show',
                    'update-status',
                ]),
                'recall'           => array_merge($readWrite, ['send-reminder']),

                // ── Patients ──────────────────────────────────────────
                'patient'          => $basicCrud,
                'medical-profile'  => $readWrite,
                'medical-alert'    => $readOnly,

                // ── Doctors & Schedules ───────────────────────────────
                'doctor'           => $readOnly,
                'doctor-schedule'  => $readOnly,

                // ── Branch ────────────────────────────────────────────
                // Read-only, same reason as the dentist role: the branch
                // picker on booking/inventory forms calls GET /branches.
                'branch'           => $readOnly,

                // ── Treatment Plans ───────────────────────────────────
                'treatment-plan'   => array_merge($readOnly, ['send-to-patient', 'record-consumables']),

                // ── Consent Forms ─────────────────────────────────────
                'consent-form'     => array_merge($readOnly, ['send', 'print']),

                // ── Financial ─────────────────────────────────────────
                'invoice'          => array_merge($basicCrud, ['send', 'mark-paid', 'print']),
                'payment'          => array_merge($basicCrud, ['print-receipt']),
                'discount'         => $readOnly,

                // ── Attachments ───────────────────────────────────────
                'attachment'       => array_merge($readWrite, ['upload', 'download']),

                // ── Inventory (read-only) ─────────────────────────────
                'inventory'        => $readOnly,
                'item'             => $readOnly,

                // ── Landing Page ──────────────────────────────────────
                'service'          => $readOnly,
                'gallery'          => $readOnly,
                'faq'              => $readOnly,

                // ── HR (own records only) ─────────────────────────────
                'attendance'       => ['viewAny', 'view', 'check-in', 'check-out'],
                'leave-request'    => ['viewAny', 'view', 'create'],

                // ── Communications ────────────────────────────────────
                'sms'              => ['send', 'view'],
                'email'            => ['send', 'view'],
                'reminder'         => ['viewAny', 'view', 'send'],

                // ── Reports ───────────────────────────────────────────
                'report'           => ['view'],              
                'patient-report'   => ['view'],           
                'financial-report' => ['view'],         
            ],

            // ═══════════════════════════════════════════════════════════
            // 🧑 PATIENT — Self-service portal
            // ═══════════════════════════════════════════════════════════
            // database/seeders/RolePermissionSeeder.php
            'patient' => [
                // No dashboard: it is the staff overview. Patients land on
                // their appointments instead. Re-adding it here would put it
                // back on every reseed, since roles are synced, not merged.
                'notification'     => ['viewAny', 'view', 'update'],

                // Own appointments only (no viewAny — that's for staff)
                'appointment'      => ['view', 'create', 'cancel', 'reschedule'],

                // Own profile only
                'patient'          => ['view', 'update'],
                'medical-profile'  => ['view', 'update'],

                // Own clinical records (read-only)
                'dental-chart'     => ['view'],
                // 'treatment'        => ['view'],
                'treatment-plan'   => ['view', 'accept', 'reject', 'viewOwn'],
                // RoleSeeder — patient role
                'prescription'     => ['viewOwn'],

                // Browse clinic directory — needed to choose a dentist and
                // branch when booking. Not patient data, so listing is safe.
                // 'doctor'           => ['viewAny', 'view'],
                // 'branch'           => ['viewAny', 'view'],

                // Browse info (single item, not lists)
                'service'          => ['view'],
                'faq'              => ['view'],

                // Own billing only
                'invoice'          => ['view'],
                'payment'          => ['view', 'create'],
            ],
        ];

        // Map alias roles
        $roles['doctor'] = $roles['dentist'];
        $roles['staff']  = $roles['receptionist'];

        // ═══════════════════════════════════════════════════════════════
        // ASSIGN PERMISSIONS TO ROLES
        // ═══════════════════════════════════════════════════════════════

        foreach ($roles as $roleName => $resources) {

            $role = Role::firstOrCreate([
                'name'       => $roleName,
                'guard_name' => 'api',
            ]);

            // Super admin gets everything
            if ($resources === '*') {
                $allPermissions = Permission::where('guard_name', 'api')->get();
                $role->syncPermissions($allPermissions);
                $this->command->info("✅ {$roleName}: " . $allPermissions->count() . " permissions (ALL)");
                continue;
            }

            // Build flat permission list from resource => actions map
            $permissions = [];
            foreach ($resources as $resource => $actions) {
                foreach ($actions as $action) {
                    $permissions[] = "{$resource}.{$action}";
                }
            }

            // Only assign permissions that actually exist in DB
            $existingPermissions = Permission::where('guard_name', 'api')
                ->whereIn('name', $permissions)
                ->pluck('name')
                ->toArray();

            // Warn about any that were requested but don't exist
            $missing = array_diff($permissions, $existingPermissions);
            if (!empty($missing)) {
                foreach ($missing as $m) {
                    $this->command->warn("   ⚠️  [{$roleName}] missing permission: {$m}");
                }
            }

            $role->syncPermissions($existingPermissions);
            $this->command->info("✅ {$roleName}: " . count($existingPermissions) . " permissions assigned");
        }

        $this->command->newLine();
        $this->command->info('🎉 All roles configured successfully!');
    }
}
