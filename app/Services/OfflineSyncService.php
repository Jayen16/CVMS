<?php

namespace App\Services;

use App\Models\ChildProfile;
use App\Models\OfflineSyncOutbox;
use App\Models\User;
use App\Models\VaccinationRecord;
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

        $entity = match ($model::class) {
            ChildProfile::class => 'children',
            VaccinationRecord::class => 'immunization_records',
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

        $entity = match ($model::class) {
            ChildProfile::class => 'children',
            VaccinationRecord::class => 'immunization_records',
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
            default => ['sync_uuid' => $model->sync_uuid],
        };
    }

    /** @return array{processed: int, failed: int} */
    public function syncPending(): array
    {
        try {
            $result = app(FacilitySyncService::class)->synchronize();

            return ['processed' => $result['processed'], 'failed' => $result['failed']];
        } catch (\Throwable $exception) {
            report($exception);

            return ['processed' => 0, 'failed' => 1];
        }
    }
}
