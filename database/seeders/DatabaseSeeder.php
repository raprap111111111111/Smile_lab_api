<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call the seeders in order
        $this->call([
            PermissionSeeder::class,    
            RoleSeeder::class,          
            RolePermissionSeeder::class, 
            SuperAdminSeeder::class,
            BranchSeeder::class,
            UserSeeder::class,
            RecallTypeSeeder::class,
            DoctorSeeder::class,
            ToothConditionSeeder::class,
            TreatmentSeeder::class,  
            ConsentTemplateSeeder::class, 
            SettingSeeder::class,
            NotificationTemplateSeeder::class,
            ServiceSeeder::class,
            GallerySeeder::class,
            DoctorScheduleSeeder::class
        ]);

        $this->ensurePassportPersonalClient();
    }

    private function ensurePassportPersonalClient(): void
    {
        $hasClient = \Laravel\Passport\Client::where('grant_types', 'like', '%personal_access%')
            ->where('provider', 'users')
            ->where('revoked', false)
            ->exists();

        if (!$hasClient) {
            \Illuminate\Support\Facades\Artisan::call('passport:client', [
                '--personal' => true,
                '--name' => 'Smile Lab Personal Access Client',
                '--provider' => 'users',
                '--no-interaction' => true,
            ]);
            $this->command->info('🔑 Passport Personal Access Client created automatically.');
        }
    }
}