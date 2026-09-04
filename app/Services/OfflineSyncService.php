<?php

namespace App\Services;

use App\Models\ChildProfile;
use App\Models\ChildAppointment;
use App\Models\ChildTransferHistory;
use App\Models\FacilityChildGuardian;
use App\Models\FacilityGuardian;
use App\Models\OfflineSyncOutbox;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineInventoryTransaction;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Creates durable local events for the facility-to-central push phase.
 */
class OfflineSyncService
{
    public function shouldQueue(): bool
    {
        return (bool) config('offline.enabled');
    }

    public function queueUpsert(Model $model): void
    {
        if (! $this->shouldQueue()) {
            return;
        }

        // Temporarily disabled; retain child-transfer synchronization for future use.
        if ($model instanceof ChildTransferHistory) {
            return;
        }

        $entity = match ($model::class) {
            ChildProfile::class => 'children',
            VaccinationRecord::class => 'immunization_records',
            VaccineInventoryTransaction::class => 'inventory_transactions',
            ChildAppointment::class => 'appointments',
            // ChildTransferHistory::class => 'child_transfers',
            AuditLog::class => 'audit_events',
            default => 'unsupported',
        };

        OfflineSyncOutbox::create([
            'event_uuid' => (string) Str::uuid(),
            'entity' => $entity,
            'model_type' => $model::class,
            'model_sync_uuid' => $model->sync_uuid,
            'operation' => $model->wasRecentlyCreated ? 'created' : 'updated',
            'version' => (int) ($model->sync_version ?: 1),
            'status' => 'pending',
            'payload' => $this->payloadFor($model),
            'queued_at' => now(),
        ]);
    }

    public function queueDelete(Model $model): void
    {
        if (! $this->shouldQueue()) {
            return;
        }

        // Temporarily disabled; retain child-transfer synchronization for future use.
        if ($model instanceof ChildTransferHistory) {
            return;
        }

        $entity = match ($model::class) {
            ChildProfile::class => 'children',
            VaccinationRecord::class => 'immunization_records',
            VaccineInventoryTransaction::class => 'inventory_transactions',
            ChildAppointment::class => 'appointments',
            // ChildTransferHistory::class => 'child_transfers',
            default => 'unsupported',
        };

        OfflineSyncOutbox::create([
            'event_uuid' => (string) Str::uuid(),
            'entity' => $entity,
            'model_type' => $model::class,
            'model_sync_uuid' => $model->sync_uuid,
            'operation' => 'deleted',
            'version' => (int) ($model->sync_version ?: 1),
            'status' => 'pending',
            'payload' => ['sync_uuid' => $model->sync_uuid, 'version' => (int) ($model->sync_version ?: 1)],
            'queued_at' => now(),
        ]);
    }

    public function queueStaff(User $user): void
    {
        if (! $this->shouldQueue()) {
            return;
        }

        $payload = ['name' => $user->name, 'role' => $user->role, 'active' => (bool) $user->is_active];
        $latest = OfflineSyncOutbox::query()->where('entity', 'facility_staff')->where('model_sync_uuid', $user->id)->latest('created_at')->first();
        if ($latest && $latest->payload === $payload) {
            return;
        }

        OfflineSyncOutbox::create([
            'event_uuid' => (string) Str::uuid(), 'entity' => 'facility_staff', 'model_type' => User::class,
            'model_sync_uuid' => $user->id, 'operation' => 'updated', 'version' => 1, 'status' => 'pending',
            'payload' => $payload, 'queued_at' => now(),
        ]);
    }

    public function queueGuardian(User $user): void
    {
        if (! $this->shouldQueue() || ! $user->isParent()) return;
        $this->queueEvent('guardians', $user->id, User::class, 'updated', [
            'guardian_uuid' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'active' => (bool) $user->is_active,
        ]);
    }

    public function queueRelationship(ChildProfile $child, User $parent, string $relationship, string $operation = 'updated'): void
    {
        if (! $this->shouldQueue()) return;
        $this->queueEvent('child_guardian_relationships', $child->sync_uuid.'|'.$parent->id, ChildProfile::class, $operation, [
            'child_uuid' => $child->sync_uuid, 'guardian_uuid' => $parent->id, 'relationship' => $relationship,
        ]);
    }

    public function queueAudit(AuditLog $audit): void
    {
        if (! $this->shouldQueue() || ! in_array($audit->event, ['created', 'updated', 'deleted', 'verified', 'rejected', 'archived'], true)) return;
        $this->queueEvent('audit_events', $audit->id, AuditLog::class, 'created', [
            'event' => $audit->event, 'auditable_type' => $audit->auditable_type, 'auditable_id' => $audit->auditable_id,
            'description' => $audit->description, 'old_values' => $audit->old_values, 'new_values' => $this->redact($audit->new_values ?? []),
            'actor_uuid' => $audit->user_id, 'actor_name' => $audit->user?->name,
        ]);
    }

    public function queueNotification(User $recipient, array $payload): void
    {
        if (! $this->shouldQueue()) return;
        $this->queueEvent('notification_requests', (string) Str::uuid(), User::class, 'created', [
            'recipient_uuid' => $recipient->id, 'notification_type' => 'in_app', 'payload' => $payload,
        ]);
    }

    private function redact(array $values): array
    {
        foreach (['password', 'password_confirmation', 'remember_token', 'token'] as $key) unset($values[$key]);
        return $values;
    }

    private function queueEvent(string $entity, string $recordUuid, string $modelType, string $operation, array $payload): void
    {
        OfflineSyncOutbox::create([
            'event_uuid' => (string) Str::uuid(), 'entity' => $entity, 'model_type' => $modelType,
            'model_sync_uuid' => $recordUuid, 'operation' => $operation, 'version' => 1, 'status' => 'pending',
            'payload' => $payload, 'queued_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function payloadFor(Model $model): array
    {
        return match ($model::class) {
            ChildProfile::class => [
                'sync_uuid' => $model->sync_uuid,
                'barangay_name' => $model->barangay?->name,
                'municipality_code' => $model->barangay?->municipalityRelation?->code,
                'first_name' => $model->first_name,
                'middle_name' => $model->middle_name,
                'last_name' => $model->last_name,
                'birthdate' => $model->birthdate?->toDateString(),
                'sex' => $model->sex,
                'guardian_name' => $model->guardian_name,
                'guardian_contact' => $model->guardian_contact,
                'address' => $model->address,
                'vaccine_card_token' => $model->vaccine_card_token,
                'registered_by_uuid' => $model->registered_by_uuid ?: $model->created_by,
                'registered_by_name' => $model->registered_by_name ?: $model->creator?->name,
                'registered_by_role' => $model->registered_by_role ?: $model->creator?->role,
                'version' => (int) ($model->sync_version ?: 1),
                'updated_at' => $model->updated_at?->toIso8601String(),
            ],
            VaccinationRecord::class => [
                'sync_uuid' => $model->sync_uuid,
                'child_sync_uuid' => $model->child?->sync_uuid,
                'vaccine_code' => $model->vaccineType?->code,
                'dose_number' => $model->dose_number,
                'source' => $model->source,
                'verification_status' => $model->verification_status,
                'administered_at' => $model->administered_at?->toDateString(),
                'verified_at' => $model->verified_at?->toIso8601String(),
                'clinic_name' => $model->clinic_name,
                'clinic_location' => $model->clinic_location,
                'proof_path' => $model->proof_path,
                'proof_paths' => $model->proofPaths(),
                'client_submission_id' => $model->client_submission_id,
                'next_due_at' => $model->next_due_at?->toDateString(),
                'suggested_vaccine' => $model->suggested_vaccine,
                'suggested_schedule_version_id' => $model->suggested_schedule_version_id,
                'suggestion_note' => $model->suggestion_note,
                'remarks' => $model->remarks,
                'administered_by_uuid' => $model->administered_by_uuid ?: $model->recorded_by,
                'recorded_by_uuid' => $model->recorded_by_uuid ?: $model->recorded_by,
                'administered_by_name' => $model->administered_by_name ?: $model->recorder?->name,
                'recorded_by_name' => $model->recorded_by_name ?: $model->recorder?->name,
                'recorded_by_role' => $model->recorded_by_role ?: $model->recorder?->role,
                'version' => (int) ($model->sync_version ?: 1),
                'updated_at' => $model->updated_at?->toIso8601String(),
            ],
            VaccineInventoryTransaction::class => [
                'sync_uuid' => $model->sync_uuid, 'barangay_name' => $model->barangay?->name,
                'vaccine_code' => $model->vaccineType?->code, 'item_code' => $model->inventoryItem?->item_code,
                'batch_number' => $model->batch_number, 'expiry_date' => $model->expiry_date?->toDateString(),
                'transaction_type' => $model->transaction_type, 'movement' => $model->movement, 'quantity' => $model->quantity,
                'transaction_date' => $model->transaction_date?->toDateString(), 'reference_number' => $model->reference_number,
                'recorded_by_uuid' => $model->recorded_by, 'recorded_by_name' => $model->recorder?->name,
                'recorded_by_role' => $model->recorder?->role, 'notes' => $model->notes, 'version' => (int) ($model->sync_version ?: 1),
            ],
            ChildAppointment::class => [
                'child_uuid' => $model->child?->sync_uuid, 'vaccine_code' => $model->vaccineType?->code,
                'scheduled_for' => $model->scheduled_for?->toIso8601String(), 'status' => $model->status, 'notes' => $model->notes,
                'created_by_uuid' => $model->created_by, 'created_by_name' => $model->created_by_name, 'created_by_role' => $model->created_by_role,
                'version' => (int) ($model->sync_version ?: 1),
            ],
            ChildTransferHistory::class => [
                'child_uuid' => $model->child_sync_uuid, 'facility_uuid' => $model->facility_uuid,
                'from_barangay_name' => $model->from_barangay_name, 'to_barangay_name' => $model->to_barangay_name,
                'municipality_code' => $model->municipality_code, 'transferred_by_uuid' => $model->transferred_by_uuid,
                'transferred_by_name' => $model->transferred_by_name, 'transferred_by_role' => $model->transferred_by_role,
                'transferred_at' => $model->transferred_at?->toIso8601String(), 'reason' => $model->reason,
                'version' => (int) ($model->sync_version ?: 1), 'updated_at' => $model->updated_at?->toIso8601String(),
            ],
            default => ['sync_uuid' => $model->sync_uuid],
        };
    }

    /** @return array{processed: int, failed: int} */
    public function syncPending(): array
    {
        try {
            $result = app(FacilitySyncService::class)->synchronize();

            // FacilitySyncService reports the number uploaded under `pushed`.
            // Expose that count as `processed` to the manual-sync controller.
            return ['processed' => $result['pushed'], 'failed' => $result['failed']];
        } catch (\Throwable $exception) {
            report($exception);

            if ($exception instanceof \Illuminate\Http\Client\RequestException
                && in_array($exception->response?->status(), [401, 403], true)
                && config('system.instance_type') === 'facility') {
                app(FacilityActivationService::class)->localInstallation()->update(['status' => 'suspended']);
            }

            return ['processed' => 0, 'failed' => 1];
        }
    }
}
