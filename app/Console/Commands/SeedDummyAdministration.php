<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\Municipality;
use App\Models\PopulationBackground;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SeedDummyAdministration extends Command
{
    protected $signature = 'cvms:seed-dummy-administration {--dry-run : Roll back all database writes}';

    protected $description = 'Add dummy population targets, archived samples, and named admin/nurse accounts for Indang';

    private const MARKER = 'CVMS dummy administration v1';

    public function handle(): int
    {
        Notification::fake();
        Mail::fake();
        Bus::fake();
        Http::preventStrayRequests();

        $barangays = Barangay::whereIn('municipality_id', Municipality::where('name', 'Indang')->select('id'))
            ->orderBy('name')->get();
        $vaccine = VaccineType::where('active', true)->orderBy('code')->first();
        if ($barangays->isEmpty() || ! $vaccine) {
            $this->error('Indang barangays and an active vaccine are required.');

            return self::FAILURE;
        }

        $hash = Hash::make('password123');
        $summary = [];
        DB::beginTransaction();
        try {
            Model::withoutEvents(function () use ($barangays, $vaccine, $hash, &$summary): void {
                foreach ($barangays as $index => $barangay) {
                    $slug = Str::slug(trim($barangay->name), '');
                    $staff = [];
                    foreach (['admin' => 'barangay_admin', 'nurse' => 'nurse'] as $suffix => $role) {
                        $email = $slug.'-'.$suffix.'@example.com';
                        $user = User::unguarded(fn () => User::firstOrCreate(['email' => $email], [
                            'name' => trim($barangay->name).' '.ucfirst($suffix),
                            'password' => $hash, 'login_channel' => 'email',
                            'role' => $role, 'roles' => [$role],
                            'permissions' => $role === 'nurse' ? User::defaultNursePermissions() : null,
                            'barangay_id' => $barangay->id, 'municipality_id' => $barangay->municipality_id,
                            'is_active' => true, 'email_verified_at' => now(), 'invitation_accepted_at' => now(),
                        ]));
                        if ($user->barangay_id !== $barangay->id || ! $user->hasRole($role) || ! $user->is_active) {
                            throw new \RuntimeException('Account conflict: '.$email.'. No accounts were overwritten.');
                        }
                        $staff[$suffix] = $user;
                    }

                    // Synthetic denominators, not official population statistics.
                    foreach (range(now()->year - 2, now()->year) as $year) {
                        foreach (['0–11 months', '1–4 years'] as $ageIndex => $ageGroup) {
                            foreach (['female', 'male'] as $sexIndex => $sex) {
                                PopulationBackground::firstOrCreate([
                                    'barangay_id' => $barangay->id, 'reference_year' => $year,
                                    'age_group' => $ageGroup, 'sex' => $sex,
                                ], [
                                    'municipality_id' => $barangay->municipality_id,
                                    'target_population' => ($ageIndex === 0 ? 35 : 140) + ($index * 7 % 60) + $sexIndex * 4 + ($year - now()->year + 2) * 3,
                                    'source' => self::MARKER.' | Synthetic targets for testing only',
                                    'created_by' => $staff['admin']->id, 'updated_by' => $staff['admin']->id,
                                ]);
                            }
                        }
                    }

                    $child = ChildProfile::withoutGlobalScope('not_archived')->firstOrCreate([
                        'barangay_id' => $barangay->id, 'address' => self::MARKER.' | Archived sample',
                    ], [
                        'created_by' => $staff['nurse']->id,
                        'first_name' => 'Demo', 'middle_name' => 'Archived', 'last_name' => trim($barangay->name),
                        'birthdate' => today()->subYears(2), 'sex' => $index % 2 ? 'male' : 'female',
                        'guardian_name' => 'Dummy Guardian',
                        'sync_uuid' => (string) Str::uuid(), 'vaccine_card_token' => (string) Str::uuid(), 'sync_version' => 1,
                        'archived_at' => now(), 'archived_by' => $staff['admin']->id, 'archive_reason' => 'other',
                    ]);

                    if ($index === 0) {
                        // The Archive Center lists vaccinations for active children.
                        $reportChild = ChildProfile::withoutGlobalScope('not_archived')->firstOrCreate([
                            'barangay_id' => $barangay->id, 'address' => self::MARKER.' | Report sample',
                        ], [
                            'created_by' => $staff['nurse']->id,
                            'first_name' => 'Demo', 'middle_name' => 'Report', 'last_name' => trim($barangay->name),
                            'birthdate' => $child->birthdate, 'sex' => $child->sex, 'guardian_name' => 'Dummy Guardian',
                            'sync_uuid' => (string) Str::uuid(), 'vaccine_card_token' => (string) Str::uuid(), 'sync_version' => 1,
                        ]);
                        $archive = [
                            'archived_at' => now(), 'archived_by' => $staff['admin']->id,
                            'archive_reason' => self::MARKER.' | Synthetic archive example',
                        ];
                        VaccinationRecord::withoutGlobalScope('not_archived')->firstOrCreate([
                            'child_profile_id' => $reportChild->id, 'remarks' => self::MARKER.' | Archived vaccination sample',
                        ], [
                            'vaccine_type_id' => $vaccine->id, 'dose_number' => 1,
                            'administered_at' => $child->birthdate->copy()->addMonth(),
                            'recorded_by' => $staff['nurse']->id, 'verified_by' => $staff['nurse']->id,
                            'verified_at' => now(), 'source' => 'outside_clinic', 'verification_status' => 'verified',
                            'clinic_name' => 'Dummy Outside Clinic', 'clinic_location' => 'Indang, Cavite',
                            'sync_uuid' => (string) Str::uuid(), 'sync_version' => 1,
                            ...$archive,
                        ]);
                        AuditLog::withoutGlobalScope('not_archived')->firstOrCreate([
                            'event' => 'child_archived', 'description' => self::MARKER.' | Archived audit sample',
                        ], [
                            'user_id' => $staff['admin']->id, 'auditable_type' => ChildProfile::class,
                            'auditable_id' => $child->id, 'new_values' => ['dummy' => true, 'archive_reason' => 'other'],
                            ...$archive,
                        ]);
                    }
                    $summary[] = [trim($barangay->name), $staff['admin']->email, $staff['nurse']->email];
                }
            });
            if ($this->option('dry-run')) {
                DB::rollBack();
                $this->info('Dry run passed. All database writes rolled back.');
            } else {
                DB::commit();
                $this->info('Dummy administration data saved. New account password: password123');
            }
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
        $this->table(['Barangay', 'Admin email', 'Nurse email'], $summary);

        return self::SUCCESS;
    }
}
