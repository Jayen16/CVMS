<?php

namespace Database\Seeders;

use App\Jobs\SendVaccinationReminder;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\ChildVaccineSeriesVersion;
use App\Models\SystemInstallation;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineSchedule;
use App\Services\ImmunizationSuggestionService;
use App\Services\VaccineScheduleVersionResolver;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Standalone SMS demo fixture; never include in the general database seeder. */
class YvonneCosteloReminderSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::transaction(function (): void {
            $parent = User::query()->whereIn('phone', [
                '09156839837', '+639156839837', '639156839837',
            ])->sole();

            if (! $parent->isParent() || $parent->isArchived()) {
                throw new RuntimeException('The registered phone must belong to an unarchived parent account.');
            }

            $installation = SystemInstallation::query()->first();
            $barangayId = $parent->barangay_id;
            if (config('system.instance_type') !== 'central') {
                if ($installation?->status !== 'active' || blank($installation->barangay_id)) {
                    throw new RuntimeException('Activate the facility and assign its barangay before seeding this reminder.');
                }
                $barangayId = $installation->barangay_id;
            }
            if (blank($barangayId)) {
                $barangayId = Barangay::query()->where('name', 'Bancod')
                    ->whereHas('municipalityRelation', fn ($query) => $query->where('name', 'Indang'))
                    ->sole()->id;
            }

            $version = app(VaccineScheduleVersionResolver::class)->activeVersion();
            $rows = VaccineSchedule::query()->with('vaccineType')
                ->where('vaccine_schedule_version_id', $version?->id)
                ->where('active', true)->whereHas('vaccineType', fn ($query) => $query->where('active', true))->get();
            $ipv = $rows->first(fn ($row) => $row->vaccineType->code === 'ipv' && (int) $row->dose_number === 1);
            if ($ipv === null) {
                throw new RuntimeException('Seed the active IPV Dose 1 schedule first.');
            }

            $dueAt = Carbon::parse('2026-11-29')->startOfDay();
            $birthdate = null;
            for ($age = 0; $age <= 3660; $age++) {
                $candidate = $dueAt->copy()->subDays($age);
                if ($ipv->dueDateFromBirthdate($candidate)->equalTo($dueAt)) {
                    $birthdate = $candidate;
                    break;
                }
            }
            if ($birthdate === null) {
                throw new RuntimeException('Cannot derive a birthday for the configured IPV schedule.');
            }

            $parent->update(['name' => 'Yvonne Costelo']);
            $child = ChildProfile::withoutGlobalScopes()->firstOrNew([
                'created_by' => $parent->id, 'first_name' => 'Girl', 'last_name' => 'Costelo',
            ]);
            if ($child->exists && ($child->isArchived() || $child->birthdate->ne($birthdate))) {
                throw new RuntimeException('An existing Girl Costelo profile conflicts with this demo fixture.');
            }
            $child->fill([
                'barangay_id' => $barangayId, 'birthdate' => $birthdate,
                'sex' => 'female', 'guardian_name' => $parent->name,
                'guardian_contact' => $parent->phone,
                'sync_uuid' => $child->sync_uuid ?: (string) Str::uuid(),
                'vaccine_card_token' => $child->vaccine_card_token ?: (string) Str::uuid(),
            ])->save();
            $child->parents()->syncWithoutDetaching([$parent->id => ['relationship' => 'mother']]);

            // Synthetic history is necessary because the reminder selects only
            // the earliest missing dose. Do not treat these as clinical records.
            foreach ($rows as $row) {
                ChildVaccineSeriesVersion::firstOrCreate([
                    'child_profile_id' => $child->id, 'vaccine_type_id' => $row->vaccine_type_id,
                ], [
                    'vaccine_schedule_version_id' => $version->id, 'assigned_at' => now(),
                    'assignment_reason' => 'Yvonne Costelo SMS demo fixture',
                ]);
                $date = $row->dueDateFromBirthdate($birthdate);
                if ($row->id === $ipv->id || $date->gt($dueAt)) {
                    continue;
                }
                VaccinationRecord::firstOrCreate([
                    'child_profile_id' => $child->id, 'vaccine_type_id' => $row->vaccine_type_id,
                    'dose_number' => $row->dose_number,
                ], [
                    'recorded_by' => $parent->id, 'source' => 'outside_clinic',
                    'verification_status' => 'verified', 'administered_at' => $date->min(Carbon::today()),
                    'clinic_name' => 'SMS reminder demo',
                    'sync_uuid' => (string) Str::uuid(),
                    'remarks' => 'SYNTHETIC DEMO ONLY: seeded to exercise the IPV Dose 1 SMS reminder; not proof of vaccination.',
                ]);
            }

            $suggestion = app(ImmunizationSuggestionService::class)->suggestNextDose($child->fresh());
            if ($suggestion['vaccine_code'] !== 'ipv' || $suggestion['dose_number'] !== 1
                || $suggestion['due_at']?->toDateString() !== '2026-11-29') {
                throw new RuntimeException('Existing child history prevents IPV Dose 1 from being the next reminder. No changes saved.');
            }

            $this->command?->info('Girl Costelo: birthday '.$birthdate->toDateString().'; IPV Dose 1 due 2026-11-29.');
            $sendAt = Carbon::parse('2026-11-28 08:00:00', 'Asia/Manila');
            if (now()->lt($sendAt)) {
                if (config('queue.default') === 'sync') {
                    throw new RuntimeException('Use a persistent queue connection to delay this SMS until 08:00.');
                }
                DB::afterCommit(function () use ($child, $parent, $ipv, $sendAt): void {
                    SendVaccinationReminder::dispatch(
                        (string) $child->id, (string) $parent->id,
                        $ipv->vaccineType->name, 1, '2026-11-29', 'sms', 'one_day',
                    )->delay($sendAt);
                });
                $this->command?->info('SMS queued for 2026-11-28 08:00 Asia/Manila. Run the queue worker to deliver it.');
            } else {
                $this->command?->warn('08:00 on November 28 has passed. No delayed SMS queued; the normal reminder command applies.');
            }
        });
    }
}
