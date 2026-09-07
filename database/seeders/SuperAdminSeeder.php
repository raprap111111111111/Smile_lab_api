<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Change guard_name to 'api'
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'api'] 
        );

        // Create or get Super Admin user
        $superAdmin = User::firstOrCreate(
            ['email' => 'jaldevecais2@gmail.com'],
            [
                'name'              => 'Super Admin',
                'password'          => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        // Assign role
        $superAdmin->assignRole($superAdminRole);

        $this->command->info('Super Admin created successfully with email: jaldevecais2@gmail.com');
    }
}