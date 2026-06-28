<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\User;
use App\Models\VaccineSchedule;
use App\Models\VaccineType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = Carbon::now();

        collect(['Barangay 1', 'Barangay 2', 'Barangay 3'])->each(fn (string $name) => Barangay::firstOrCreate([
            'name' => $name,
        ]));

        $vaccines = config('immunization.vaccines');

        if (is_array($vaccines)) {
            foreach ($vaccines as $vaccine) {
                if (! is_array($vaccine)) {
                    continue;
                }

                VaccineType::updateOrCreate([
                    'code' => $vaccine['code'],
                ], [
                    'name' => $vaccine['name'],
                    'active' => true,
                ]);
            }
        }

        $schedule = config('immunization.routine_schedule');

        if (is_array($schedule)) {
            foreach ($schedule as $code => $doses) {
                if (! is_string($code) || ! is_array($doses)) {
                    continue;
                }

                $vaccine = VaccineType::where('code', $code)->first();

                if ($vaccine === null) {
                    continue;
                }

                foreach ($doses as $dose) {
                    if (! is_array($dose) || ! isset($dose['dose'], $dose['age'], $dose['label']) || ! is_array($dose['age'])) {
                        continue;
                    }

                    VaccineSchedule::updateOrCreate([
                        'vaccine_type_id' => $vaccine->id,
                        'dose_number' => (int) $dose['dose'],
                    ], [
                        'age_days' => (int) ($dose['age']['days'] ?? 0),
                        'age_weeks' => (int) ($dose['age']['weeks'] ?? 0),
                        'age_months' => (int) ($dose['age']['months'] ?? 0),
                        'age_years' => (int) ($dose['age']['years'] ?? 0),
                        'label' => (string) $dose['label'],
                        'indication' => 'routine_vaccination',
                        'active' => true,
                    ]);
                }
            }
        }

        $barangayOneId = Barangay::where('name', 'Barangay 1')->value('id');

        User::updateOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'System Admin',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'roles' => ['superadmin'],
            'is_active' => true,
            'email_verified_at' => $now,
            'invitation_accepted_at' => $now,
        ]);

        User::updateOrCreate([
            'email' => 'barangay-admin@example.com',
        ], [
            'name' => 'Barangay Admin',
            'password' => Hash::make('password123'),
            'role' => 'barangay_admin',
            'roles' => ['barangay_admin'],
            'barangay_id' => $barangayOneId,
            'is_active' => true,
            'email_verified_at' => $now,
            'invitation_accepted_at' => $now,
        ]);

        User::updateOrCreate([
            'email' => 'nurse@example.com',
        ], [
            'name' => 'Demo Nurse',
            'password' => Hash::make('password123'),
            'role' => 'nurse',
            'roles' => ['nurse'],
            'barangay_id' => $barangayOneId,
            'is_active' => true,
            'email_verified_at' => $now,
            'invitation_accepted_at' => $now,
        ]);

        User::updateOrCreate([
            'email' => 'parent@example.com',
        ], [
            'name' => 'Demo Parent',
            'password' => Hash::make('password123'),
            'phone' => '09171234567',
            'role' => 'parent',
            'roles' => ['parent'],
            'is_active' => true,
            'email_verified_at' => $now,
            'invitation_accepted_at' => $now,
        ]);
    }
}
