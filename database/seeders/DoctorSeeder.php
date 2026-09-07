<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Doctor;
use App\Models\Branch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DoctorSeeder extends Seeder
{
    /**
     * Doctor emails — used for cleanup before re-seeding.
     */
    private array $emails = [
        'jal@gmail.com',
        'maria.santos@smilelab.com',
        'john.delacruz@smilelab.com',
    ];

    public function run(): void
    {
        $this->cleanUp();

        $this->seedDoctor(
            name:              'Dr. Juvile Ann Legislador Mansader',
            email:             'jal@gmail.com',
            phone:             '09943665968',
            licenseNumber:     '112627',
            specialization:    'General Dentistry',
            bio:               'Experienced dental practitioner with 10+ years of experience in general and cosmetic dentistry.',
            consultationFee:   500.00,
            yearsOfExperience: 10,
            branchIds:         [1, 2],
        );

        $this->command->newLine();
        $this->command->info('🎉 All doctors seeded successfully!');
    }

    /**
     * Wipe existing doctor seed data so re-runs never hit duplicate keys.
     */
    private function cleanUp(): void
    {
        $userIds = User::whereIn('email', $this->emails)->pluck('id');

        if ($userIds->isEmpty()) {
            $this->command->line('   ℹ️  No existing doctor records found — skipping cleanup.');
            return;
        }

        // 1. Delete doctor profiles first
        Doctor::whereIn('user_id', $userIds)->delete();

        // 2. Clear branch pivot rows
        DB::table('branch_user')
            ->whereIn('user_id', $userIds)
            ->delete();

        // 3. Detach Spatie roles
        DB::table('model_has_roles')
            ->whereIn('model_id', $userIds)
            ->where('model_type', User::class)
            ->delete();

        // 4. Delete users last
        User::whereIn('id', $userIds)->delete();

        $this->command->line('   🧹 Cleaned up ' . $userIds->count() . ' existing doctor record(s).');
    }

    /**
     * Create a fresh doctor — user account, doctor profile, branches, role.
     */
    private function seedDoctor(
        string $name,
        string $email,
        string $phone,
        string $licenseNumber,
        string $specialization,
        string $bio,
        float  $consultationFee,
        int    $yearsOfExperience,
        array  $branchIds,
    ): void {
        $user = User::create([
            'name'              => $name,
            'email'             => $email,
            'phone'             => $phone,
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        Doctor::create([
            'user_id'             => $user->id,
            'license_number'      => $licenseNumber,
            'specialization'      => $specialization,
            'bio'                 => $bio,
            'consultation_fee'    => $consultationFee,
            'years_of_experience' => $yearsOfExperience,
        ]);

        $this->syncBranches($user->id, $branchIds);

        $this->assignRole($user, 'dentist');

        $this->command->info("   ✅ {$name} — {$specialization}");
    }

    /**
     * Insert branch_user pivot rows for a user.
     */
    private function syncBranches(int $userId, array $branchIds): void
    {
        if (empty($branchIds)) {
            $this->command->warn("   ⚠️  No branch IDs provided for user #{$userId}");
            return;
        }

        $existingIds = Branch::whereIn('id', $branchIds)
            ->pluck('id')
            ->toArray();

        $missing = array_diff($branchIds, $existingIds);

        if (!empty($missing)) {
            $this->command->warn(
                '   ⚠️  Branch IDs not found: ' . implode(', ', $missing)
            );
        }

        if (empty($existingIds)) {
            $this->command->warn("   ⚠️  No valid branches for user #{$userId}");
            return;
        }

        $rows = array_map(
            fn (int $branchId) => [
                'user_id'    => $userId,
                'branch_id'  => $branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            $existingIds
        );

        DB::table('branch_user')->insert($rows);

        $this->command->line(
            '   🏢 Branches assigned: ' . implode(', ', $existingIds)
        );
    }

    /**
     * Assign a Spatie role to a user safely.
     */
    private function assignRole(User $user, string $roleName): void
    {
        try {
            $user->syncRoles([$roleName]);
        } catch (\Throwable $e) {
            $this->command->warn(
                "   ⚠️  Could not assign role '{$roleName}' to {$user->email}: {$e->getMessage()}"
            );
        }
    }
}