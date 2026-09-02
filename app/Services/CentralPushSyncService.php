<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\FacilityConnection;
use App\Models\FacilityStaff;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Client;

class CentralPushSyncService
{
    /** @param array<int, array<string, mixed>> $events @return array{accepted: array<int, string>} */
    public function push(array $events, ?Client $client): array
    {
        $connection = $client ? FacilityConnection::query()->where('passport_client_id', $client->getKey())->where('status', 'active')->first() : null;
        abort_unless($connection, 403, 'Facility connection is not active.');

        $accepted = [];
        DB::transaction(function () use ($events, $connection, &$accepted): void {
            foreach ($events as $event) {
                if (! in_array($event['entity'], ['facility_staff', 'children', 'immunization_records'], true)) {
                    abort(422, 'Unsupported synchronization entity.');
                }

                if (DB::table('sync_processed_events')->where('event_uuid', $event['event_uuid'])->exists()) {
                    $accepted[] = $event['event_uuid'];

                    continue;
                }

                match ($event['entity']) {
                    'facility_staff' => $this->applyStaff($connection->facility_id, $event),
                    'children' => $this->applyChild($connection->facility_id, $event),
                    'immunization_records' => $this->applyImmunization($connection->facility_id, $event),
                };

                DB::table('sync_processed_events')->insert([
                    'id' => (string) Str::uuid(), 'facility_id' => $connection->facility_id,
                    'event_uuid' => $event['event_uuid'], 'entity' => $event['entity'], 'record_uuid' => $event['record_uuid'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $accepted[] = $event['event_uuid'];
            }
        });

        return ['accepted' => $accepted];
    }

    /** @param array<string, mixed> $event */
    private function applyStaff(string $facilityId, array $event): void
    {
        $data = $event['data'];
        FacilityStaff::query()->updateOrCreate(['facility_id' => $facilityId, 'staff_uuid' => $event['record_uuid']], [
            'name' => $data['name'], 'role' => $data['role'], 'active' => $data['active'], 'last_seen_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $event */
    private function applyChild(string $facilityId, array $event): void
    {
        $data = $event['data'];
        $child = ChildProfile::withoutGlobalScopes()->where('sync_uuid', $event['record_uuid'])->first() ?? new ChildProfile;
        $child->forceFill([
            'sync_uuid' => $event['record_uuid'], 'facility_uuid' => $facilityId, 'barangay_id' => $this->barangayId($data), 'created_by' => null,
            'first_name' => $data['first_name'], 'middle_name' => $data['middle_name'], 'last_name' => $data['last_name'], 'birthdate' => $data['birthdate'],
            'sex' => $data['sex'], 'guardian_name' => $data['guardian_name'], 'guardian_contact' => $data['guardian_contact'], 'address' => $data['address'],
            'vaccine_card_token' => $data['vaccine_card_token'], 'registered_by_uuid' => $data['registered_by_uuid'], 'registered_by_name' => $data['registered_by_name'],
            'registered_by_role' => $data['registered_by_role'], 'sync_version' => $event['version'], 'archived_at' => $event['operation'] === 'deleted' ? now() : null,
        ])->save();
    }

    /** @param array<string, mixed> $event */
    private function applyImmunization(string $facilityId, array $event): void
    {
        $data = $event['data'];
        $childId = ChildProfile::withoutGlobalScopes()->where('sync_uuid', $data['child_sync_uuid'])->value('id');
        $vaccineId = VaccineType::query()->where('code', $data['vaccine_code'])->value('id');
        abort_unless($childId && $vaccineId, 422, 'Immunization dependencies are missing.');
        $record = VaccinationRecord::withoutGlobalScopes()->where('sync_uuid', $event['record_uuid'])->first() ?? new VaccinationRecord;
        $record->forceFill([
            'sync_uuid' => $event['record_uuid'], 'facility_uuid' => $facilityId, 'child_profile_id' => $childId, 'vaccine_type_id' => $vaccineId,
            'recorded_by' => null, 'submitted_by' => null, 'verified_by' => null, 'dose_number' => $data['dose_number'], 'source' => $data['source'],
            'verification_status' => $data['verification_status'], 'administered_at' => $data['administered_at'], 'verified_at' => $data['verified_at'],
            'clinic_name' => $data['clinic_name'], 'clinic_location' => $data['clinic_location'], 'proof_path' => $data['proof_path'], 'proof_paths' => $data['proof_paths'],
            'client_submission_id' => $data['client_submission_id'], 'next_due_at' => $data['next_due_at'], 'suggested_vaccine' => $data['suggested_vaccine'],
            'suggested_schedule_version_id' => $data['suggested_schedule_version_id'], 'suggestion_note' => $data['suggestion_note'], 'remarks' => $data['remarks'],
            'administered_by_uuid' => $data['administered_by_uuid'], 'recorded_by_uuid' => $data['recorded_by_uuid'], 'administered_by_name' => $data['administered_by_name'],
            'recorded_by_name' => $data['recorded_by_name'], 'recorded_by_role' => $data['recorded_by_role'], 'sync_version' => $event['version'],
        ])->save();
    }

    /** @param array<string, mixed> $data */
    private function barangayId(array $data): string
    {
        $id = Barangay::query()->where('name', $data['barangay_name'])->whereHas('municipalityRelation', fn ($query) => $query->where('code', $data['municipality_code']))->value('id');

        abort_unless($id, 422, 'Child facility location is missing on the central server.');

        return (string) $id;
    }
}
