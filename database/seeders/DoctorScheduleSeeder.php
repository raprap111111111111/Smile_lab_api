<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class DoctorScheduleSeeder extends Seeder
{
    /**
     * day_of_week convention (Carbon / Laravel standard):
     * 0 = Sunday, 1 = Monday, 2 = Tuesday, 3 = Wednesday,
     * 4 = Thursday, 5 = Friday, 6 = Saturday
     */
    public function run(): void
    {
        $this->cleanUp();

        $doctor = Doctor::whereHas('user', function ($q) {
            $q->where('email', 'jal@gmail.com');
        })->first();

        if (!$doctor) {
            $this->command->error('❌ Doctor not found. Run DoctorSeeder first.');
            return;
        }

        $murcia  = Branch::find(1); // Murcia Branch
        $felisa  = Branch::find(2); // Fellisa Branch

        if (!$murcia || !$felisa) {
            $this->command->error('❌ Branches not found. Run BranchSeeder first.');
            return;
        }

        // ────────────────────────────────────────────────
        // MURCIA BRANCH (branch_id = 1)
        // ────────────────────────────────────────────────
        $murciaSchedules = [
            // Monday: 9:00 AM – 2:00 PM
            ['day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '14:00:00'],
            // Tuesday: 1:00 PM – 5:00 PM
            ['day_of_week' => 2, 'start_time' => '13:00:00', 'end_time' => '17:00:00'],
            // Wednesday: NO CLINIC
            // Thursday: 9:00 AM – 2:00 PM
            ['day_of_week' => 4, 'start_time' => '09:00:00', 'end_time' => '14:00:00'],
            // Friday: 9:00 AM – 2:00 PM
            ['day_of_week' => 5, 'start_time' => '09:00:00', 'end_time' => '14:00:00'],
            // Saturday: 9:00 AM – 2:00 PM
            ['day_of_week' => 6, 'start_time' => '09:00:00', 'end_time' => '14:00:00'],
            // Sunday: 1:00 PM – 5:00 PM
            ['day_of_week' => 0, 'start_time' => '13:00:00', 'end_time' => '17:00:00'],
        ];

        foreach ($murciaSchedules as $schedule) {
            DoctorSchedule::create([
                'doctor_id'   => $doctor->id,
                'branch_id'   => $murcia->id,
                'day_of_week' => $schedule['day_of_week'],
                'start_time'  => $schedule['start_time'],
                'end_time'    => $schedule['end_time'],
            ]);
        }

        $this->command->info("   ✅ Murcia schedules created (" . count($murciaSchedules) . " days)");

        // ────────────────────────────────────────────────
        // FELISA / FELLISA BRANCH (branch_id = 2)
        // ────────────────────────────────────────────────
        $felisaSchedules = [
            // Monday: 2:30 PM – 6:00 PM
            ['day_of_week' => 1, 'start_time' => '14:30:00', 'end_time' => '18:00:00'],
            // Tuesday: NO CLINIC
            // Wednesday: NO CLINIC
            // Thursday: 2:30 PM – 6:00 PM
            ['day_of_week' => 4, 'start_time' => '14:30:00', 'end_time' => '18:00:00'],
            // Friday: 2:30 PM – 6:00 PM
            ['day_of_week' => 5, 'start_time' => '14:30:00', 'end_time' => '18:00:00'],
            // Saturday: 2:30 PM – 6:00 PM
            ['day_of_week' => 6, 'start_time' => '14:30:00', 'end_time' => '18:00:00'],
            // Sunday: 9:00 AM – 1:00 PM
            ['day_of_week' => 0, 'start_time' => '09:00:00', 'end_time' => '13:00:00'],
        ];

        foreach ($felisaSchedules as $schedule) {
            DoctorSchedule::create([
                'doctor_id'   => $doctor->id,
                'branch_id'   => $felisa->id,
                'day_of_week' => $schedule['day_of_week'],
                'start_time'  => $schedule['start_time'],
                'end_time'    => $schedule['end_time'],
            ]);
        }

        $this->command->info("   ✅ Felisa schedules created (" . count($felisaSchedules) . " days)");

        $this->command->newLine();
        $this->command->info('🎉 Doctor schedules seeded successfully!');
    }

    /**
     * Remove existing schedules so the seeder is safe to re-run.
     */
    private function cleanUp(): void
    {
        $count = DoctorSchedule::count();

        if ($count === 0) {
            $this->command->line('   ℹ️  No existing schedules found — skipping cleanup.');
            return;
        }

        DoctorSchedule::query()->delete();
        $this->command->line("   🧹 Cleaned up {$count} existing schedule record(s).");
    }
}