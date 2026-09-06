<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name'        => 'super-admin',
                'guard_name'  => 'api',
                'description' => 'Full system access — system owner only',
            ],
            [
                'name'        => 'superadmin',
                'guard_name'  => 'api',
                'description' => 'Full system access — system owner only',
            ],
            [
                'name'        => 'admin',
                'guard_name'  => 'api',
                'description' => 'Clinic owner or branch administrator',
            ],
            [
                'name'        => 'dentist',       
                'guard_name'  => 'api',
                'description' => 'Dental practitioner / doctor',
            ],
            [
                'name'        => 'doctor',       
                'guard_name'  => 'api',
                'description' => 'Doctor / Dental practitioner',
            ],
            [
                'name'        => 'receptionist',
                'guard_name'  => 'api',
                'description' => 'Front desk staff',
            ],
            [
                'name'        => 'staff',
                'guard_name'  => 'api',
                'description' => 'Clinic staff member',
            ],
            [
                'name'        => 'patient',
                'guard_name'  => 'api',
                'description' => 'Registered patient (self-service portal)',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                [
                    'name'       => $role['name'],
                    'guard_name' => $role['guard_name'],
                ],
                [
                    'description' => $role['description'],
                    'is_active'   => true,
                ]
            );

            $this->command->info("   ✅ Role ready: {$role['name']}");
        }

        $this->command->newLine();
        $this->command->info('🎉 All roles seeded successfully!');
    }
}