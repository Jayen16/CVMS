<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\ChildVaccineSeriesVersion;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineInventoryItem;
use App\Models\VaccineInventoryTransaction;
use App\Models\VaccineSchedule;
use App\Models\VaccineScheduleVersion;
use App\Models\VaccineType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a small, repeatable dataset specifically for the predictive analytics page.
 * It bootstraps the local vaccine/schedule definitions when they are missing.
 */
class PredictiveAnalyticsDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $barangay = Barangay::query()->whereNotNull('municipality_id')->where('name', 'Bancod')->first()
            ?? Barangay::query()->whereNotNull('municipality_id')->first()
            ?? Barangay::query()->where('name', 'Barangay 1')->first()
            ?? Barangay::query()->first()
            ?? Barangay::create(['name' => 'Barangay 1']);
        $version = VaccineScheduleVersion::query()->where('status', 'active')->orderByDesc('effective_date')->first();

        if ($version === null) {
            throw new \RuntimeException('No schedule version exists. Run the migrations first: php artisan migrate.');
        }

        $vaccines = [];
        foreach (config('immunization.vaccines', []) as $definition) {
            $vaccines[$definition['code']] = VaccineType::updateOrCreate(
                ['code' => $definition['code']],
                ['name' => $definition['name'], 'active' => true],
            );
        }
        foreach (config('immunization.routine_schedule', []) as $code => $doses) {
            $vaccine = $vaccines[$code] ?? null;
            foreach ($doses as $dose) {
                VaccineSchedule::updateOrCreate(
                    ['vaccine_schedule_version_id' => $version->id, 'vaccine_type_id' => $vaccine?->id, 'dose_number' => $dose['dose']],
                    ['age_days' => $dose['age']['days'] ?? 0, 'age_weeks' => $dose['age']['weeks'] ?? 0, 'age_months' => $dose['age']['months'] ?? 0, 'age_years' => $dose['age']['years'] ?? 0, 'label' => $dose['label'], 'indication' => 'routine_vaccination', 'active' => true],
                );
            }
        }

        $staff = $this->user('Predictive Demo RHU', 'predictive.demo.admin@example.com', '+639170000001', 'municipal_admin', null, $barangay->municipality_id);
        $parents = [
            'sms' => $this->user('Demo Parent SMS', 'predictive.parent.sms@example.com', '+639170000002', 'parent'),
            'email' => $this->user('Demo Parent Email', 'predictive.parent.email@example.com', null, 'parent'),
            'both' => $this->user('Demo Parent Both', 'predictive.parent.both@example.com', '+639170000003', 'parent'),
            'none' => $this->user('Demo Parent No Contact', null, null, 'parent'),
        ];

        $dtap = $this->vaccine('dtap');
        $pcv = $this->vaccine('pcv');
        $mmr = $this->vaccine('mmr');
        $today = Carbon::today();

        // One complete child provides a healthy historical baseline.
        $complete = $this->child('pa-complete', 'Paolo', 'Complete', $today->copy()->subMonths(20), $barangay, $staff, $parents['both']);
        $this->assign($complete, $version, [$dtap, $pcv, $mmr]);
        $this->record($complete, $staff, $dtap, 1, $today->copy()->subMonths(18)->toDateString());
        $this->record($complete, $staff, $dtap, 2, $today->copy()->subMonths(17)->toDateString());
        $this->record($complete, $staff, $dtap, 3, $today->copy()->subMonths(16)->toDateString());
        $this->record($complete, $staff, $pcv, 1, $today->copy()->subMonths(18)->toDateString());
        $this->record($complete, $staff, $pcv, 2, $today->copy()->subMonths(17)->toDateString());
        $this->record($complete, $staff, $pcv, 3, $today->copy()->subMonths(16)->toDateString());
        $this->record($complete, $staff, $mmr, 1, $today->copy()->subMonths(11)->toDateString());

        // Delayed history plus an uncompleted next dose produces measurable risk.
        $delayed = $this->child('pa-delayed', 'Dina', 'Delayed', $today->copy()->subMonths(14), $barangay, $staff, $parents['sms']);
        $this->assign($delayed, $version, [$dtap, $pcv, $mmr]);
        $this->record($delayed, $staff, $dtap, 1, $today->copy()->subMonths(12)->addDays(14)->toDateString());
        $this->record($delayed, $staff, $dtap, 2, $today->copy()->subMonths(11)->addDays(18)->toDateString());
        $this->record($delayed, $staff, $pcv, 1, $today->copy()->subMonths(12)->addDays(12)->toDateString());

        // This child has no contact method, exercising the access-signal feature.
        $missed = $this->child('pa-missed', 'Miko', 'Missed', $today->copy()->subMonths(10), $barangay, $staff, $parents['none']);
        $this->assign($missed, $version, [$dtap, $pcv]);
        $this->record($missed, $staff, $dtap, 1, $today->copy()->subMonths(8)->toDateString());

        // Recent birth cohorts create upcoming scheduled demand.
        foreach ([
            ['pa-cohort-1', 'Ana', 'Cohort', 45, 'email'],
            ['pa-cohort-2', 'Ben', 'Cohort', 60, 'sms'],
            ['pa-cohort-3', 'Cara', 'Cohort', 75, 'both'],
            ['pa-cohort-4', 'Don', 'Cohort', 90, 'email'],
        ] as [$key, $first, $last, $ageDays, $parentKey]) {
            $child = $this->child($key, $first, $last, $today->copy()->subDays($ageDays), $barangay, $staff, $parents[$parentKey]);
            $this->assign($child, $version, [$dtap, $pcv]);
            $this->record($child, $staff, $dtap, 1, $today->copy()->subDays($ageDays - 42)->toDateString(), $key === 'pa-cohort-1' ? 'pending' : 'verified');
        }

        $item = VaccineInventoryItem::updateOrCreate(
            ['item_code' => 'PA-DEMO-DTAP-001'],
            ['barangay_id' => $barangay->id, 'vaccine_type_id' => $dtap->id, 'batch_number' => 'PA-DEMO-2026', 'expiry_date' => $today->copy()->addYear(), 'received_at' => $today->copy()->subMonths(2), 'reference_number' => 'PA-DEMO-RECEIPT', 'notes' => 'Predictive analytics demonstration stock.'],
        );
        $this->transaction($item, $barangay, $dtap, $staff, 'receipt', 'in', 100, 'PA-DEMO-RECEIPT');
        $this->transaction($item, $barangay, $dtap, $staff, 'usage', 'out', 8, 'PA-DEMO-USAGE');

        $this->command?->info('Predictive analytics demo data is ready. Login: predictive.demo.admin@example.com / password123');
    }

    private function user(string $name, ?string $email, ?string $phone, string $role, ?Barangay $barangay = null, ?string $municipalityId = null): User
    {
        $identity = $email !== null ? ['email' => $email] : ['name' => $name, 'role' => $role];

        return User::updateOrCreate($identity, [
            'name' => $name, 'phone' => $phone, 'password' => Hash::make('password123'), 'role' => $role,
            'roles' => [$role], 'barangay_id' => $barangay?->id, 'municipality_id' => $municipalityId, 'is_active' => true,
            'email_verified_at' => now(), 'invitation_accepted_at' => now(),
        ]);
    }

    private function child(string $key, string $first, string $last, Carbon $birthdate, Barangay $barangay, User $staff, User $parent): ChildProfile
    {
        $child = ChildProfile::updateOrCreate(
            ['first_name' => $first, 'last_name' => $last, 'birthdate' => $birthdate->toDateString(), 'barangay_id' => $barangay->id, 'address' => 'Predictive Analytics Demo'],
            ['created_by' => $staff->id, 'sex' => 'unspecified', 'guardian_name' => $parent->name, 'guardian_contact' => $parent->phone],
        );
        $child->parents()->syncWithoutDetaching([$parent->id => ['relationship' => 'guardian']]);
        return $child;
    }

    private function assign(ChildProfile $child, VaccineScheduleVersion $version, array $vaccines): void
    {
        foreach ($vaccines as $vaccine) {
            ChildVaccineSeriesVersion::updateOrCreate(
                ['child_profile_id' => $child->id, 'vaccine_type_id' => $vaccine->id],
                ['vaccine_schedule_version_id' => $version->id, 'assigned_at' => now(), 'assignment_reason' => 'predictive_demo_seed'],
            );
        }
    }

    private function record(ChildProfile $child, User $staff, VaccineType $vaccine, int $dose, string $date, string $status = 'verified'): VaccinationRecord
    {
        return VaccinationRecord::updateOrCreate(
            ['child_profile_id' => $child->id, 'vaccine_type_id' => $vaccine->id, 'dose_number' => $dose, 'administered_at' => $date, 'source' => 'barangay_clinic'],
            ['recorded_by' => $staff->id, 'verified_by' => $status === 'verified' ? $staff->id : null, 'verification_status' => $status, 'verified_at' => $status === 'verified' ? now() : null, 'clinic_name' => 'Predictive Demo RHU', 'clinic_location' => 'Demo clinic'],
        );
    }

    private function transaction(VaccineInventoryItem $item, Barangay $barangay, VaccineType $vaccine, User $staff, string $type, string $movement, int $quantity, string $reference): void
    {
        VaccineInventoryTransaction::updateOrCreate(
            ['sync_uuid' => 'predictive-demo-'.$type],
            ['barangay_id' => $barangay->id, 'vaccine_type_id' => $vaccine->id, 'vaccine_inventory_item_id' => $item->id, 'recorded_by' => $staff->id, 'transaction_type' => $type, 'movement' => $movement, 'quantity' => $quantity, 'batch_number' => $item->batch_number, 'expiry_date' => $item->expiry_date, 'transaction_date' => today(), 'reference_number' => $reference, 'notes' => 'Predictive analytics demonstration transaction.'],
        );
    }

    private function vaccine(string $code): VaccineType
    {
        return VaccineType::query()->where('code', $code)->firstOrFail();
    }
}
