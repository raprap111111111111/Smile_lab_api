<?php
// database/seeders/BranchSeeder.php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            [
                'id'            => 1,
                'name'          => 'Murcia Branch',
                'branch_code'   => 'MUR-001',
                'address'       => '123 Main Street, Murcia',
                'city'          => 'Murcia',
                'province'      => 'Negros Occidental',
                'phone'         => '0344 555 1234',
                'email'         => 'murcia@smilelab.com',
                'opening_hours' => 'Mon-Sat 9:00 AM - 6:00 PM',
                'is_active'     => true,
            ],
            [
                'id'            => 2,
                'name'          => 'Fellisa Branch',
                'branch_code'   => 'FEL-002',
                'address'       => '456 Fellisa Avenue, Bacolod',
                'city'          => 'Bacolod',
                'province'      => 'Negros Occidental',
                'phone'         => '0344 555 5678',
                'email'         => 'fellisa@smilelab.com',
                'opening_hours' => 'Mon-Fri 8:00 AM - 5:00 PM',
                'is_active'     => true,
            ],
        ];

        foreach ($branches as $branchData) {
            Branch::updateOrCreate(
                ['id' => $branchData['id']],
                $branchData
            );
        }

        $this->command->info('✅ Branches seeded successfully!');
    }
}