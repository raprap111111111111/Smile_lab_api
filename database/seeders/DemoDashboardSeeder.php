<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Fills the dashboard charts with plausible traffic: patients registered across
 * the last six months, plus appointments across the last two weeks and today.
 *
 * Deliberately touches no invoices or payments — the revenue counter stays on
 * whatever real billing data exists.
 *
 * NOT wired into DatabaseSeeder — it writes demo rows, so run it deliberately:
 *
 *     php artisan db:seed --class=DemoDashboardSeeder
 */
class DemoDashboardSeeder extends Seeder
{
    private const REASONS = [
        'Routine Cleaning',
        'Tooth Extraction',
        'Root Canal',
        'Dental Filling',
        'Braces Adjustment',
        'Consultation',
        'Teeth Whitening',
    ];

    public function run(): void
    {
        $branch = Branch::first();
        $doctors = Doctor::all();
        $staff = User::role('admin')->first() ?? User::first();

        if (!$branch || $doctors->isEmpty() || !$staff) {
            $this->command->warn(
                'DemoDashboardSeeder needs a branch, a doctor and a staff user. Run db:seed first.'
            );
            return;
        }

        $patients = $this->seedPatients($branch->id);
        $appointments = $this->seedAppointments($patients, $doctors, $branch->id, $staff->id);

        $this->command->info(
            "Seeded {$patients->count()} patients and {$appointments->count()} appointments."
        );
    }

    /**
     * Patients spread across the last six months so the monthly registration
     * chart has a shape rather than a single spike.
     */
    private function seedPatients(int $branchId)
    {
        $perMonth = [4, 7, 5, 9, 6, 8];
        $patients = collect();

        foreach ($perMonth as $monthsAgo => $count) {
            $month = Carbon::today()->subMonths(5 - $monthsAgo);

            for ($i = 0; $i < $count; $i++) {
                $createdAt = $month->copy()
                    ->startOfMonth()
                    ->addDays(random_int(0, min(27, $month->daysInMonth - 1)))
                    ->addHours(random_int(8, 17));

                // Keep it inside the current month for the newest bucket.
                if ($createdAt->isFuture()) {
                    $createdAt = Carbon::now()->subHours(random_int(1, 48));
                }

                $suffix = $createdAt->format('ymd') . $i . random_int(100, 999);

                $patient = User::create([
                    'name'              => $this->fakeName(),
                    'email'             => "demo.patient.{$suffix}@smilelab.test",
                    'password'          => Hash::make('password'),
                    'phone'             => '09' . random_int(100000000, 999999999),
                    'branch_id'         => $branchId,
                    'email_verified_at' => $createdAt,
                ]);

                $patient->assignRole('patient');

                // created_at is set by the model, so backdate it explicitly.
                $patient->forceFill([
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ])->saveQuietly();

                $patients->push($patient->refresh());
            }
        }

        return $patients;
    }

    /**
     * Two weeks of history plus a full day today, so both the daily trend and
     * the hourly distribution have something to draw.
     */
    private function seedAppointments($patients, $doctors, int $branchId, int $staffId)
    {
        $appointments = collect();

        for ($daysAgo = 13; $daysAgo >= 0; $daysAgo--) {
            $day = Carbon::today()->subDays($daysAgo);

            // Sundays are quiet; weekdays busier than Saturdays.
            $bookings = match (true) {
                $day->isSunday()   => random_int(0, 1),
                $day->isSaturday() => random_int(2, 4),
                default            => random_int(4, 9),
            };

            for ($i = 0; $i < $bookings; $i++) {
                $start = $day->copy()
                    ->setTime(random_int(8, 17), [0, 15, 30, 45][random_int(0, 3)]);
                $duration = [30, 45, 60][random_int(0, 2)];

                $patient = $patients->random();
                $doctor = $doctors->random();

                $appointment = Appointment::create([
                    'user_id'          => $patient->id,
                    'doctor_id'        => $doctor->id,
                    'branch_id'        => $branchId,
                    'patient_name'     => $patient->name,
                    'patient_phone'    => $patient->phone,
                    'patient_email'    => $patient->email,
                    'start_time'       => $start,
                    'end_time'         => $start->copy()->addMinutes($duration),
                    'status'           => $this->statusFor($start),
                    'reason_for_visit' => self::REASONS[array_rand(self::REASONS)],
                    'created_by'       => $staffId,
                ]);

                $appointments->push($appointment);
            }
        }

        return $appointments;
    }

    /** Past slots are settled; today's and later are still pending/confirmed. */
    private function statusFor(Carbon $start): string
    {
        if ($start->isPast() && !$start->isToday()) {
            return random_int(1, 10) <= 8
                ? AppointmentStatus::COMPLETED->value
                : AppointmentStatus::CANCELLED->value;
        }

        return random_int(1, 10) <= 7
            ? AppointmentStatus::CONFIRMED->value
            : AppointmentStatus::PENDING->value;
    }

    private function fakeName(): string
    {
        $first = ['Maria', 'Jose', 'Ana', 'Paulo', 'Liza', 'Ramon', 'Carla', 'Miguel', 'Grace', 'Nico', 'Beatriz', 'Andres'];
        $last = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Torres', 'Villanueva', 'Aquino', 'Ramos'];

        return $first[array_rand($first)] . ' ' . $last[array_rand($last)];
    }
}
