<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // ─── Business ─────────────────────────────
            ['key' => 'clinic_name',   'value' => 'Smile Concept Dental', 'group' => 'business', 'type' => 'string',  'label' => 'Clinic Name',   'is_public' => true],
            ['key' => 'clinic_phone',  'value' => '+63 917 123 4567',     'group' => 'business', 'type' => 'string',  'label' => 'Clinic Phone',  'is_public' => true],
            ['key' => 'clinic_email',  'value' => 'info@smilelab.ph', 'group' => 'business', 'type' => 'string',  'label' => 'Clinic Email',  'is_public' => true],
            ['key' => 'clinic_address','value' => '123 Ayala Ave, Makati','group' => 'business', 'type' => 'string',  'label' => 'Address',       'is_public' => true],

            // ─── Booking: slot shape ──────────────────
            // is_public rows are served unauthenticated via GET /api/public/settings,
            // which the patient-facing booking form depends on.
            ['key' => 'appointment_slot_duration',  'value' => '30',            'group' => 'booking', 'type' => 'integer', 'label' => 'Default Slot Duration (minutes)',   'description' => 'Sets the standard appointment length.',                          'is_public' => true],
            ['key' => 'appointment_buffer_minutes', 'value' => '0',             'group' => 'booking', 'type' => 'integer', 'label' => 'Buffer Between Slots (minutes)',   'description' => 'Adds extra time between appointments for cleaning and preparation.', 'is_public' => true],
            ['key' => 'clinic_opens_at',            'value' => '09:00',         'group' => 'booking', 'type' => 'string',  'label' => 'Clinic Opens At',                  'description' => 'Defines the earliest appointment time.',                         'is_public' => true],
            ['key' => 'clinic_closes_at',           'value' => '18:00',         'group' => 'booking', 'type' => 'string',  'label' => 'Clinic Closes At',                 'description' => 'Defines the latest appointment time.',                           'is_public' => true],
            ['key' => 'lunch_break_start',          'value' => '12:00',         'group' => 'booking', 'type' => 'string',  'label' => 'Lunch Break Start',                'description' => 'Starts the period when no appointments can be scheduled.',       'is_public' => true],
            ['key' => 'lunch_break_end',            'value' => '13:00',         'group' => 'booking', 'type' => 'string',  'label' => 'Lunch Break End',                  'description' => 'Ends the lunch break and resumes scheduling.',                   'is_public' => true],
            ['key' => 'working_days',               'value' => '[1,2,3,4,5,6]', 'group' => 'booking', 'type' => 'json',    'label' => 'Working Days',                     'description' => 'Specifies the days when the clinic accepts appointments. Sunday is 0.', 'is_public' => true],

            // ─── Booking: capacity caps ───────────────
            ['key' => 'max_appointments_per_dentist_per_day', 'value' => '12', 'group' => 'booking', 'type' => 'integer', 'label' => 'Max Appointments Per Dentist Per Day', 'description' => 'Limits the number of patients each dentist can see daily.'],
            ['key' => 'max_appointments_per_day',             'value' => '60', 'group' => 'booking', 'type' => 'integer', 'label' => 'Max Clinic-Wide Appointments Per Day', 'description' => 'Limits the total number of appointments for the entire clinic.'],
            ['key' => 'max_concurrent_appointments',          'value' => '3',  'group' => 'booking', 'type' => 'integer', 'label' => 'Max Concurrent Appointments',         'description' => 'Limits how many appointments can happen at the same time.'],

            // ─── Booking: window ──────────────────────
            ['key' => 'booking_lead_time_hours',                'value' => '2',  'group' => 'booking', 'type' => 'integer', 'label' => 'Minimum Advance Booking (hours)', 'description' => 'Sets how many hours in advance a patient can book.',                    'is_public' => true],
            ['key' => 'max_advance_booking_days',               'value' => '90', 'group' => 'booking', 'type' => 'integer', 'label' => 'Maximum Advance Booking (days)',  'description' => 'Limits how far into the future a patient can schedule an appointment.', 'is_public' => true],
            ['key' => 'allow_same_day_booking',                 'value' => '1',  'group' => 'booking', 'type' => 'boolean', 'label' => 'Allow Same-Day Booking',          'description' => 'Allows or prevents appointments from being booked on the same day.',    'is_public' => true],
            ['key' => 'max_future_appointments_per_patient',    'value' => '3',  'group' => 'booking', 'type' => 'integer', 'label' => 'Max Future Appointments Per Patient', 'description' => 'Prevents patients from making too many future bookings.'],
            ['key' => 'allow_online_booking',                   'value' => '1',  'group' => 'booking', 'type' => 'boolean', 'label' => 'Allow Online Booking',            'description' => 'Master switch for patient self-service booking.',                       'is_public' => true],

            // ─── Cancellation & no-show ───────────────
            // Fees and the block threshold are stored and editable but NOT yet enforced:
            // charging requires Invoice integration and blocking requires a NO_SHOW status.
            ['key' => 'cancellation_window_hours', 'value' => '24',   'group' => 'cancellation', 'type' => 'integer', 'label' => 'Cancellation Cutoff (hours)',   'description' => 'Sets the deadline for appointment cancellations.', 'is_public' => true],
            ['key' => 'late_cancellation_fee',     'value' => '0.00', 'group' => 'cancellation', 'type' => 'float',   'label' => 'Late Cancellation Fee',         'description' => 'Charges patients for canceling after the deadline. Not yet enforced.'],
            ['key' => 'no_show_fee',               'value' => '0.00', 'group' => 'cancellation', 'type' => 'float',   'label' => 'No-Show Fee',                   'description' => 'Charges patients who do not attend their appointment. Not yet enforced.'],
            ['key' => 'no_shows_before_block',     'value' => '3',    'group' => 'cancellation', 'type' => 'integer', 'label' => 'No-Shows Before Patient Block', 'description' => 'Automatically flags or blocks patients after multiple no-shows. Not yet enforced.'],

            // ─── Billing ──────────────────────────────
            ['key' => 'tax_rate',       'value' => '12.00', 'group' => 'billing', 'type' => 'float',  'label' => 'Tax Rate (%)'],
            ['key' => 'currency',       'value' => 'PHP',   'group' => 'billing', 'type' => 'string', 'label' => 'Currency', 'is_public' => true],
            ['key' => 'invoice_prefix', 'value' => 'INV',   'group' => 'billing', 'type' => 'string', 'label' => 'Invoice Prefix', 'is_editable' => false],

            // ─── Reminders & transactional email ──────
            // reminder_offsets is [first, second] hours before the appointment.
            ['key' => 'reminder_offsets',  'value' => '[24,1]',           'group' => 'reminders', 'type' => 'json',    'label' => 'Reminder Offsets (hours)', 'description' => 'First and second reminder, in hours before the appointment.'],
            ['key' => 'reminder_channels', 'value' => '["email","sms"]',  'group' => 'reminders', 'type' => 'json',    'label' => 'Reminder Channels'],
            ['key' => 'sms_enabled',       'value' => '1',                'group' => 'reminders', 'type' => 'boolean', 'label' => 'SMS Enabled'],
            ['key' => 'email_enabled',     'value' => '1',                'group' => 'reminders', 'type' => 'boolean', 'label' => 'Email Enabled', 'description' => 'Master switch for all patient email.'],

            ['key' => 'send_booking_confirmation_email', 'value' => '1',  'group' => 'reminders', 'type' => 'boolean', 'label' => 'Send Booking Confirmation Email', 'description' => 'Sends an email after an appointment is booked.'],
            ['key' => 'send_cancellation_email',         'value' => '1',  'group' => 'reminders', 'type' => 'boolean', 'label' => 'Send Cancellation Email',         'description' => 'Sends an email when an appointment is canceled.'],
            ['key' => 'send_followup_email',             'value' => '0',  'group' => 'reminders', 'type' => 'boolean', 'label' => 'Send Follow-Up Email',            'description' => 'Sends an aftercare or feedback email after the appointment.'],
            ['key' => 'followup_email_hours_after',      'value' => '24', 'group' => 'reminders', 'type' => 'integer', 'label' => 'Follow-Up Email Delay (hours)',   'description' => 'How long after an appointment ends the follow-up email is sent.'],

            // ─── Waitlist ─────────────────────────────
            // Stored and editable, but no waitlist subsystem exists yet.
            ['key' => 'enable_waitlist',              'value' => '0',   'group' => 'waitlist', 'type' => 'boolean', 'label' => 'Enable Waitlist',                 'description' => 'Allows patients to join a waiting list when schedules are full. Not yet enforced.'],
            ['key' => 'waitlist_offer_window_minutes','value' => '120', 'group' => 'waitlist', 'type' => 'integer', 'label' => 'Waitlist Offer Window (minutes)', 'description' => 'Sets how long a patient has to accept an available slot. Not yet enforced.'],

            // ─── Branding (frontend theme) ────────────
            ['key' => 'primary_color',   'value' => '#2563eb', 'group' => 'branding', 'type' => 'string', 'label' => 'Primary Color',   'is_public' => true],
            ['key' => 'secondary_color', 'value' => '#f59e0b', 'group' => 'branding', 'type' => 'string', 'label' => 'Secondary Color', 'is_public' => true],
            ['key' => 'logo_url',        'value' => '/logo.png','group'=> 'branding', 'type' => 'string', 'label' => 'Logo URL',        'is_public' => true],

            // ─── Inventory ────────────────────────────
            ['key' => 'inventory_auto_deduct_enabled',  'value' => '1',  'group' => 'inventory', 'type' => 'boolean', 'label' => 'Deduct Stock Automatically',        'description' => 'Draws supplies from stock when an appointment is completed, using each treatment\'s consumables list.'],
            ['key' => 'inventory_allow_negative_stock', 'value' => '1',  'group' => 'inventory', 'type' => 'boolean', 'label' => 'Allow Negative Stock',              'description' => 'Records a shortfall when supplies run out rather than hiding it. Never blocks an appointment either way.'],
            ['key' => 'inventory_track_expiry',         'value' => '1',  'group' => 'inventory', 'type' => 'boolean', 'label' => 'Track Expiry Dates',                'description' => 'Requires an expiry date on incoming stock so batches can be consumed earliest-first.'],
            ['key' => 'inventory_expiry_warning_days',  'value' => '30', 'group' => 'inventory', 'type' => 'integer', 'label' => 'Expiry Warning Window (days)',      'description' => 'How far ahead to warn that a batch is about to expire.'],
            ['key' => 'inventory_default_minimum_threshold', 'value' => '10', 'group' => 'inventory', 'type' => 'integer', 'label' => 'Default Reorder Point',        'description' => 'Used for items that do not set their own minimum threshold.'],
            ['key' => 'inventory_low_stock_alert_enabled',   'value' => '1',  'group' => 'inventory', 'type' => 'boolean', 'label' => 'Send Low-Stock Alerts',        'description' => 'Sends a daily digest of items at or below their reorder point.'],
            ['key' => 'inventory_low_stock_alert_hour',      'value' => '8',  'group' => 'inventory', 'type' => 'integer', 'label' => 'Low-Stock Alert Hour (0-23)',  'description' => 'Hour of the day the digest is sent.'],
            ['key' => 'inventory_low_stock_cooldown_days',   'value' => '3',  'group' => 'inventory', 'type' => 'integer', 'label' => 'Low-Stock Alert Cooldown (days)', 'description' => 'How long before the same item is alerted on again.'],

            // ─── Feature Flags ────────────────────────
            ['key' => 'enable_dental_chart',   'value' => '1', 'group' => 'features', 'type' => 'boolean', 'label' => 'Enable Dental Chart'],
            ['key' => 'enable_patient_portal', 'value' => '1', 'group' => 'features', 'type' => 'boolean', 'label' => 'Enable Patient Portal'],
            ['key' => 'maintenance_mode',      'value' => '0', 'group' => 'features', 'type' => 'boolean', 'label' => 'Maintenance Mode', 'is_public' => true],
        ];

        foreach ($defaults as $data) {
            $setting = Setting::firstOrNew(['key' => $data['key']]);

            // Re-seeding refreshes metadata (label, group, description, visibility)
            // but must never clobber a value an admin has already tuned.
            $attributes = array_merge(
                ['is_public' => false, 'is_editable' => true],
                $data,
            );

            if ($setting->exists) {
                unset($attributes['value']);
            }

            // setValueAttribute() serializes against $this->type, so type must
            // land before value or a json/boolean default is stored as a raw string.
            $setting->type = $attributes['type'];

            $setting->fill($attributes)->save();
        }
    }
}