<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\User;
use App\Models\VaccineSchedule;
use App\Models\VaccineScheduleVersion;
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

        $this->call(PsgcSeeder::class);

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
        $versionConfig = config('immunization.version', []);
        $scheduleVersion = VaccineScheduleVersion::updateOrCreate([
            'version_code' => $versionConfig['version_code'] ?? '2026.1',
        ], [
            'name' => $versionConfig['name'] ?? 'PIDSP 2026 Revised July',
            'effective_date' => $versionConfig['effective_date'] ?? '2026-07-01',
            'status' => $versionConfig['status'] ?? 'active',
            'source' => config('immunization.source'),
            'source_url' => config('immunization.source_url'),
            'notes' => $versionConfig['notes'] ?? null,
            'published_at' => $now,
        ]);

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
                        'vaccine_schedule_version_id' => $scheduleVersion->id,
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

        $indang = Municipality::query()->where('name', 'Indang')->firstOrFail();
        $bancod = Barangay::query()->where('municipality_id', $indang->id)->where('name', 'Bancod')->firstOrFail();
        $kaytapos = Barangay::query()->where('municipality_id', $indang->id)->where('name', 'Kaytapos')->firstOrFail();

        $instantUser = function (string $email, string $name, string $role, ?string $municipalityId = null, ?string $barangayId = null) use ($now): void {
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make('password123'),
                'role' => $role,
                'roles' => [$role],
                'permissions' => $role === 'nurse' ? User::defaultNursePermissions() : null,
                'municipality_id' => $municipalityId,
                'barangay_id' => $barangayId,
                'is_active' => true,
                'email_verified_at' => $now,
                'invitation_accepted_at' => $now,
            ]);
        };

        $instantUser('superadmin@example.com', 'Super Admin', 'superadmin');
        $instantUser('municipality@example.com', 'Indang Admin', 'municipal_admin', $indang->id);
        $instantUser('barangay-bancod@example.com', 'Barangay Bancod', 'barangay_admin', $indang->id, $bancod->id);
        $instantUser('nurse-bancod@example.com', 'Nurse Bancod', 'nurse', $indang->id, $bancod->id);
        $instantUser('nurse-bancod2@example.com', 'Nurse Bancod Second', 'nurse', $indang->id, $bancod->id);
        $instantUser('nurse-kaytapos@example.com', 'Nurse Kaytapos', 'nurse', $indang->id, $kaytapos->id);

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

        $this->call(DemoDataSeeder::class);
    }
}
