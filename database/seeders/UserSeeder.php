<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Branch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure required branches (1 and 2) exist
        $branchIds = [1, 2];
        foreach ($branchIds as $id) {
            Branch::firstOrCreate(
                ['id' => $id],
                [
                    'name' => "Smile Concept Branch " . $id,
                    'branch_code' => "SC-BR" . $id,
                    'address' => "Default Branch Address " . $id,
                    'is_active' => true,
                ]
            );
        }

        // Dentist accounts are owned by DoctorSeeder — it creates the matching
        // `doctors` profile row too. Seeding one here as well meant whichever
        // seeder ran last decided the password.

        // 2. Create the Receptionist / front desk user
        $receptionist = User::updateOrCreate(
            ['email' => 'receptionist@smilelab.com'],
            [
                'name'              => 'Front Desk Receptionist',
                'password'          => Hash::make('password'),
                'phone'             => '0900 000 0000',
                'email_verified_at' => now(),
            ]
        );

        $receptionist->branches()->sync($branchIds);
        $receptionist->assignRole('receptionist');

        // 3. Create the Clinic Admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@smilelab.com'],
            [
                'name'              => 'Clinic Administrator',
                'password'          => Hash::make('password'),
                'phone'             => '0900 000 0001',
                'email_verified_at' => now(),
            ]
        );

        $admin->branches()->sync($branchIds);
        $admin->assignRole('admin');
    }
}