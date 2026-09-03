<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\ChildTransferHistory;
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
                if (! in_array($event['entity'], ['facility_staff', 'children', 'child_transfers', 'immunization_records', 'guardians', 'child_guardian_relationships', 'inventory_transactions', 'appointments', 'audit_events', 'notification_requests'], true)) {
                    abort(422, 'Unsupported synchronization entity.');
                }

                if (DB::table('sync_processed_events')->where('event_uuid', $event['event_uuid'])->exists()) {
                    $accepted[] = $event['event_uuid'];

                    continue;
                }

                $applied = match ($event['entity']) {
                    'facility_staff' => $this->applyStaff($connection->facility_id, $event),
                    'children' => $this->applyChild($connection->facility_id, $event),
                    'child_transfers' => $this->applyChildTransfer($connection->facility_id, $event),
                    'immunization_records' => $this->applyImmunization($connection->facility_id, $event),
                    'guardians' => $this->applyGuardian($connection->facility_id, $event),
                    'child_guardian_relationships' => $this->applyRelationship($connection->facility_id, $event),
                    'inventory_transactions' => $this->applyInventoryTransaction($connection->facility_id, $event),
                    'appointments' => $this->applyAppointment($connection->facility_id, $event),
                    'audit_events' => $this->applyAuditEvent($connection->facility_id, $event),
                    'notification_requests' => $this->applyNotificationRequest($connection->facility_id, $event),
                };

                DB::table('sync_processed_events')->insert([
                    'id' => (string) Str::uuid(), 'facility_id' => $connection->facility_id,
                    'event_uuid' => $event['event_uuid'], 'entity' => $event['entity'], 'record_uuid' => $event['record_uuid'], 'operation' => $event['operation'],
                    'version' => $event['version'], 'outcome' => $applied ? 'applied' : 'stale', 'applied_at' => $applied ? now() : null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $accepted[] = $event['event_uuid'];
            }
        });

        return ['accepted' => $accepted];
    }

    /** @param array<string, mixed> $event */
    private function applyStaff(string $facilityId, array $event): bool
    {
        $data = $event['data'];
        FacilityStaff::query()->updateOrCreate(['facility_id' => $facilityId, 'staff_uuid' => $event['record_uuid']], [
            'name' => $data['name'], 'role' => $data['role'], 'active' => $data['active'], 'last_seen_at' => now(),
        ]);

        return true;
    }

    /** @param array<string, mixed> $event */
    private function applyChild(string $facilityId, array $event): bool
    {
        $data = $event['data'];
        $child = ChildProfile::withoutGlobalScopes()->where('sync_uuid', $event['record_uuid'])->first() ?? new ChildProfile;
        if ($child->exists && (int) $child->sync_version >= (int) $event['version']) {
            return false;
        }

        if ($event['operation'] === 'deleted') {
            if (! $child->exists) {
                return false;
            }

            $child->forceFill(['archived_at' => now(), 'sync_version' => $event['version']])->saveQuietly();

            return true;
        }

        $child->forceFill([
            'sync_uuid' => $event['record_uuid'], 'facility_uuid' => $facilityId, 'barangay_id' => $this->barangayId($data), 'created_by' => null,
            'first_name' => $data['first_name'], 'middle_name' => $data['middle_name'], 'last_name' => $data['last_name'], 'birthdate' => $data['birthdate'],
            'sex' => $data['sex'], 'guardian_name' => $data['guardian_name'], 'guardian_contact' => $data['guardian_contact'], 'address' => $data['address'],
            'vaccine_card_token' => $data['vaccine_card_token'], 'registered_by_uuid' => $data['registered_by_uuid'], 'registered_by_name' => $data['registered_by_name'],
            'registered_by_role' => $data['registered_by_role'], 'sync_version' => $event['version'], 'archived_at' => null,
        ])->saveQuietly();

        return true;
    }

    /** @param array<string, mixed> $event */
    private function applyImmunization(string $facilityId, array $event): bool
    {
        $data = $event['data'];
        $record = VaccinationRecord::withoutGlobalScopes()->where('sync_uuid', $event['record_uuid'])->first() ?? new VaccinationRecord;
        if ($record->exists && (int) $record->sync_version >= (int) $event['version']) {
            return false;
        }

        if ($event['operation'] === 'deleted') {
            if (! $record->exists) {
                return false;
            }

            $record->forceFill(['archived_at' => now(), 'sync_version' => $event['version']])->saveQuietly();

            return true;
        }

        $childId = ChildProfile::withoutGlobalScopes()->where('sync_uuid', $data['child_sync_uuid'])->value('id');
        $vaccineId = VaccineType::query()->where('code', $data['vaccine_code'])->value('id');
        abort_unless($childId && $vaccineId, 422, 'Immunization dependencies are missing.');

        $record->forceFill([
            'sync_uuid' => $event['record_uuid'], 'facility_uuid' => $facilityId, 'child_profile_id' => $childId, 'vaccine_type_id' => $vaccineId,
            'recorded_by' => null, 'submitted_by' => null, 'verified_by' => null, 'dose_number' => $data['dose_number'], 'source' => $data['source'],
            'verification_status' => $data['verification_status'], 'administered_at' => $data['administered_at'], 'verified_at' => $data['verified_at'],
            'clinic_name' => $data['clinic_name'], 'clinic_location' => $data['clinic_location'], 'proof_path' => $data['proof_path'], 'proof_paths' => $data['proof_paths'],
            'client_submission_id' => $data['client_submission_id'], 'next_due_at' => $data['next_due_at'], 'suggested_vaccine' => $data['suggested_vaccine'],
            'suggested_schedule_version_id' => $data['suggested_schedule_version_id'], 'suggestion_note' => $data['suggestion_note'], 'remarks' => $data['remarks'],
            'administered_by_uuid' => $data['administered_by_uuid'], 'recorded_by_uuid' => $data['recorded_by_uuid'], 'administered_by_name' => $data['administered_by_name'],
            'recorded_by_name' => $data['recorded_by_name'], 'recorded_by_role' => $data['recorded_by_role'], 'sync_version' => $event['version'],
        ])->saveQuietly();

        return true;
    }

    /** @param array<string, mixed> $event */
    private function applyChildTransfer(string $facilityId, array $event): bool
    {
        $data = $event['data'];
        ChildTransferHistory::query()->updateOrCreate(['id' => $event['record_uuid']], [
            'child_sync_uuid' => $data['child_uuid'], 'facility_uuid' => $facilityId,
            'from_barangay_name' => $data['from_barangay_name'], 'to_barangay_name' => $data['to_barangay_name'],
            'municipality_code' => $data['municipality_code'] ?? null, 'transferred_by_uuid' => $data['transferred_by_uuid'] ?? null,
            'transferred_by_name' => $data['transferred_by_name'] ?? null, 'transferred_by_role' => $data['transferred_by_role'] ?? null,
            'transferred_at' => $data['transferred_at'], 'reason' => $data['reason'] ?? null, 'sync_version' => $event['version'],
        ]);

        return true;
    }

    private function applyGuardian(string $facilityId, array $event): bool
    {
        $data = $event['data'];
        if ($event['operation'] === 'deleted') return false;
        DB::table('facility_guardians')->updateOrInsert(['facility_id' => $facilityId, 'guardian_uuid' => $event['record_uuid']], [
            'id' => (string) Str::uuid(), 'name' => $data['name'], 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null,
            'active' => $data['active'] ?? true, 'sync_version' => $event['version'], 'created_at' => now(), 'updated_at' => now(),
        ]);
        return true;
    }

    private function applyRelationship(string $facilityId, array $event): bool
    {
        $data = $event['data'];
        $where = ['facility_id' => $facilityId, 'child_uuid' => $data['child_uuid'], 'guardian_uuid' => $data['guardian_uuid']];
        if ($event['operation'] === 'deleted') return DB::table('facility_child_guardians')->where($where)->delete() > 0;
        DB::table('facility_child_guardians')->updateOrInsert($where, ['id' => (string) Str::uuid(), 'relationship' => $data['relationship'], 'sync_version' => $event['version'], 'created_at' => now(), 'updated_at' => now()]);
        return true;
    }

    private function applyInventoryTransaction(string $facilityId, array $event): bool
    {
        if ($event['operation'] === 'deleted') return false;
        $data = $event['data'];
        DB::table('facility_inventory_transactions')->updateOrInsert(['facility_id' => $facilityId, 'transaction_uuid' => $event['record_uuid']], [
            'id' => (string) Str::uuid(), 'barangay_name' => $data['barangay_name'] ?? null, 'vaccine_code' => $data['vaccine_code'] ?? null,
            'item_code' => $data['item_code'] ?? null, 'batch_number' => $data['batch_number'] ?? null, 'expiry_date' => $data['expiry_date'] ?? null,
            'transaction_type' => $data['transaction_type'], 'movement' => $data['movement'], 'quantity' => $data['quantity'], 'transaction_date' => $data['transaction_date'],
            'reference_number' => $data['reference_number'] ?? null, 'recorded_by_uuid' => $data['recorded_by_uuid'] ?? null, 'recorded_by_name' => $data['recorded_by_name'] ?? null,
            'recorded_by_role' => $data['recorded_by_role'] ?? null, 'notes' => $data['notes'] ?? null, 'sync_version' => $event['version'], 'created_at' => now(), 'updated_at' => now(),
        ]);
        return true;
    }

    private function applyAppointment(string $facilityId, array $event): bool
    {
        if ($event['operation'] === 'deleted') return false;
        $data = $event['data'];
        $childId = ChildProfile::withoutGlobalScopes()->where('sync_uuid', $data['child_uuid'])->value('id');
        $vaccineId = $data['vaccine_code'] ? VaccineType::where('code', $data['vaccine_code'])->value('id') : null;
        abort_unless($childId, 422, 'Appointment child dependency is missing.');
        DB::table('child_appointments')->updateOrInsert(['id' => $event['record_uuid']], [
            'child_profile_id' => $childId, 'vaccine_type_id' => $vaccineId, 'facility_uuid' => $facilityId, 'scheduled_for' => $data['scheduled_for'],
            'status' => $data['status'], 'notes' => $data['notes'] ?? null, 'created_by' => null, 'created_by_name' => $data['created_by_name'] ?? null,
            'created_by_role' => $data['created_by_role'] ?? null, 'sync_version' => $event['version'], 'created_at' => now(), 'updated_at' => now(),
        ]);
        return true;
    }

    private function applyAuditEvent(string $facilityId, array $event): bool
    {
        $data = $event['data'];
        DB::table('facility_audit_events')->updateOrInsert(['facility_id' => $facilityId, 'event_uuid' => $event['record_uuid']], [
            'id' => (string) Str::uuid(), 'event' => $data['event'], 'auditable_type' => $data['auditable_type'], 'auditable_id' => $data['auditable_id'] ?? null,
            'description' => $data['description'], 'old_values' => json_encode($data['old_values'] ?? []), 'new_values' => json_encode($data['new_values'] ?? []),
            'actor_uuid' => $data['actor_uuid'] ?? null, 'actor_name' => $data['actor_name'] ?? null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return true;
    }

    private function applyNotificationRequest(string $facilityId, array $event): bool
    {
        $data = $event['data'];
        DB::table('facility_notification_requests')->updateOrInsert(['facility_id' => $facilityId, 'notification_uuid' => $event['record_uuid']], [
            'id' => (string) Str::uuid(), 'recipient_uuid' => $data['recipient_uuid'], 'notification_type' => $data['notification_type'],
            'payload' => json_encode($data['payload'] ?? []), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return true;
    }

    /** @param array<string, mixed> $data */
    private function barangayId(array $data): string
    {
        $id = Barangay::query()->where('name', $data['barangay_name'])->whereHas('municipalityRelation', fn ($query) => $query->where('code', $data['municipality_code']))->value('id');

        abort_unless($id, 422, 'Child facility location is missing on the central server.');

        return (string) $id;
    }
}
