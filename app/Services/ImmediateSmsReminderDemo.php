<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\ChildVaccineSeriesVersion;
use App\Models\SystemInstallation;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class ImmediateSmsReminderDemo
{
    /** @return array{child: ChildProfile, parent: User, suggestion: array} */
    public function prepare(): array
    {
        return DB::transaction(fn () => Model::withoutEvents(function (): array {
            $parents = User::whereIn('phone', ['09760149526', '+639760149526', '639760149526'])->get();
            if ($parents->count() > 1) {
                throw new RuntimeException('Multiple accounts use this phone number. Resolve the duplicate before sending.');
            }
            $parent = $parents->first();
            if ($parent && (! $parent->isParent() || $parent->isArchived() || ! $parent->is_active)) {
                throw new RuntimeException('This phone number must belong to an active, unarchived parent.');
            }
            $installation = SystemInstallation::first();
            if (config('system.instance_type') !== 'central') {
                if ($installation?->status !== 'active' || ! $installation->barangay_id) {
                    throw new RuntimeException('Activate and assign the facility barangay first.');
                }
                $barangayId = $installation->barangay_id;
            } else {
                $barangayId = $parent?->barangay_id ?? Barangay::where('name', 'Bancod')
                    ->whereHas('municipalityRelation', fn ($query) => $query->where('name', 'Indang'))->sole()->id;
            }
            if (! $parent) {
                $parent = new User;
                $parent->forceFill([
                    'name' => 'SMS Demo Parent', 'phone' => '09760149526', 'login_channel' => 'sms',
                    'password' => Hash::make(Str::random(40)), 'role' => 'parent', 'roles' => ['parent'],
                    'barangay_id' => $barangayId, 'is_active' => true, 'invitation_accepted_at' => now(),
                ])->save();
            }

            $version = app(VaccineScheduleVersionResolver::class)->activeVersion();
            $rows = VaccineSchedule::with('vaccineType')->where('vaccine_schedule_version_id', $version?->id)
                ->where('active', true)->whereHas('vaccineType', fn ($query) => $query->where('active', true))->get();
            $ipv = $rows->first(fn ($row) => $row->vaccineType->code === 'ipv' && (int) $row->dose_number === 1);
            if (! $ipv) {
                throw new RuntimeException('An active IPV Dose 1 schedule is required.');
            }
            $dueAt = Carbon::tomorrow('Asia/Manila');
            $birthdate = null;
            for ($age = 1; $age <= 3660; $age++) {
                $candidate = $dueAt->copy()->subDays($age);
                if ($ipv->dueDateFromBirthdate($candidate)->equalTo($dueAt)) {
                    $birthdate = $candidate;
                    break;
                }
            }
            if (! $birthdate) {
                throw new RuntimeException('Cannot derive a birthday for the configured IPV schedule.');
            }
            $child = ChildProfile::withoutGlobalScopes()->firstOrCreate([
                'created_by' => $parent->id,
                'address' => 'SYNTHETIC SMS DEMO | 09760149526 | '.$dueAt->toDateString(),
            ], [
                'barangay_id' => $barangayId, 'first_name' => 'Baby', 'last_name' => 'Demo',
                'birthdate' => $birthdate, 'sex' => 'female',
                'guardian_name' => $parent->name, 'guardian_contact' => $parent->phone,
                'sync_uuid' => (string) Str::uuid(), 'vaccine_card_token' => (string) Str::uuid(),
            ]);
            if ($child->isArchived() || ! $child->birthdate->equalTo($birthdate)) {
                throw new RuntimeException('Existing demo child conflicts with tomorrow\'s schedule.');
            }
            $child->parents()->syncWithoutDetaching([$parent->id => ['relationship' => 'guardian']]);
            foreach ($rows as $row) {
                ChildVaccineSeriesVersion::firstOrCreate([
                    'child_profile_id' => $child->id, 'vaccine_type_id' => $row->vaccine_type_id,
                ], [
                    'vaccine_schedule_version_id' => $version->id, 'assigned_at' => now(),
                    'assignment_reason' => 'Immediate SMS reminder demo',
                ]);
                $date = $row->dueDateFromBirthdate($birthdate);
                if ($row->id === $ipv->id || $date->gt($dueAt)) {
                    continue;
                }
                VaccinationRecord::firstOrCreate([
                    'child_profile_id' => $child->id, 'vaccine_type_id' => $row->vaccine_type_id,
                    'dose_number' => $row->dose_number,
                ], [
                    'recorded_by' => $parent->id, 'source' => 'outside_clinic', 'verification_status' => 'verified',
                    'administered_at' => $date->min(Carbon::today()), 'clinic_name' => 'SMS reminder demo',
                    'sync_uuid' => (string) Str::uuid(),
                    'remarks' => 'SYNTHETIC DEMO ONLY: history for testing tomorrow\'s SMS reminder; not proof of vaccination.',
                ]);
            }
            $suggestion = app(ImmunizationSuggestionService::class)->suggestNextDose($child->fresh());
            if ($suggestion['vaccine_code'] !== 'ipv' || $suggestion['dose_number'] !== 1
                || $suggestion['due_at']?->toDateString() !== $dueAt->toDateString()) {
                throw new RuntimeException('The demo history does not produce IPV Dose 1 due tomorrow. No changes saved.');
            }

            return compact('child', 'parent', 'suggestion');
        }));
    }
}
