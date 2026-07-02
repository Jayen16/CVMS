<?php

namespace App\Services;

use App\Models\AdverseEventReport;
use App\Models\ChildProfile;
use App\Models\ClinicAnnouncement;
use App\Models\OfflineSyncOutbox;
use App\Models\VaccinationRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

        OfflineSyncOutbox::create([
            'model_type' => $model::class,
            'model_sync_uuid' => $model->sync_uuid,
            'operation' => 'upsert',
            'payload' => $this->payloadFor($model),
            'queued_at' => now(),
        ]);
    }

    public function queueDelete(Model $model): void
    {
        if (! $this->shouldQueue()) {
            return;
        }

        OfflineSyncOutbox::create([
            'model_type' => $model::class,
            'model_sync_uuid' => $model->sync_uuid,
            'operation' => 'delete',
            'payload' => [
                'sync_uuid' => $model->sync_uuid,
            ],
            'queued_at' => now(),
        ]);
    }

    public function syncPending(): array
    {
        $connection = config('offline.remote_connection', 'mysql');
        $processed = 0;
        $failed = 0;

        OfflineSyncOutbox::query()
            ->whereNull('synced_at')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($connection, &$processed, &$failed): void {
                foreach ($rows as $row) {
                    try {
                        DB::connection($connection)->transaction(function () use ($row, $connection): void {
                            $this->applyRow($row, $connection);
                        });

                        $row->update([
                            'synced_at' => now(),
                            'last_error' => null,
                            'attempts' => $row->attempts + 1,
                        ]);

                        $processed++;
                    } catch (\Throwable $exception) {
                        $row->update([
                            'last_error' => $exception->getMessage(),
                            'attempts' => $row->attempts + 1,
                        ]);

                        report($exception);
                        $failed++;
                    }
                }
            });

        return compact('processed', 'failed');
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(Model $model): array
    {
        return match ($model::class) {
            ChildProfile::class => [
                'sync_uuid' => $model->sync_uuid,
                'barangay_name' => $model->barangay?->name,
                'creator_email' => $model->creator?->email,
                'first_name' => $model->first_name,
                'middle_name' => $model->middle_name,
                'last_name' => $model->last_name,
                'birthdate' => $model->birthdate?->toDateString(),
                'sex' => $model->sex,
                'guardian_name' => $model->guardian_name,
                'guardian_contact' => $model->guardian_contact,
                'address' => $model->address,
                'vaccine_card_token' => $model->vaccine_card_token,
            ],
            VaccinationRecord::class => [
                'sync_uuid' => $model->sync_uuid,
                'child_sync_uuid' => $model->child?->sync_uuid,
                'vaccine_code' => $model->vaccineType?->code,
                'recorded_by_email' => $model->recorder?->email,
                'submitted_by_email' => $model->submitter?->email,
                'verified_by_email' => $model->verifier?->email,
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
                'suggestion_note' => $model->suggestion_note,
                'remarks' => $model->remarks,
            ],
            ClinicAnnouncement::class => [
                'sync_uuid' => $model->sync_uuid,
                'barangay_name' => $model->barangay?->name,
                'creator_email' => $model->creator?->email,
                'title' => $model->title,
                'category' => $model->category,
                'audience' => $model->audience,
                'starts_on' => $model->starts_on?->toDateString(),
                'ends_on' => $model->ends_on?->toDateString(),
                'location' => $model->location,
                'message' => $model->message,
                'active' => $model->active,
            ],
            AdverseEventReport::class => [
                'sync_uuid' => $model->sync_uuid,
                'child_sync_uuid' => $model->child?->sync_uuid,
                'vaccination_record_sync_uuid' => $model->vaccinationRecord?->sync_uuid,
                'vaccine_code' => $model->vaccineType?->code,
                'reported_by_email' => $model->reporter?->email,
                'event_date' => $model->event_date?->toDateString(),
                'severity' => $model->severity,
                'outcome' => $model->outcome,
                'symptoms' => $model->symptoms,
                'notes' => $model->notes,
            ],
            default => throw new \RuntimeException('Unsupported offline sync model ['.get_class($model).'].'),
        };
    }

    private function applyRow(OfflineSyncOutbox $row, string $connection): void
    {
        match ($row->model_type) {
            ChildProfile::class => $this->syncChildProfile($connection, $row->payload, $row->operation),
            VaccinationRecord::class => $this->syncVaccinationRecord($connection, $row->payload, $row->operation),
            ClinicAnnouncement::class => $this->syncAnnouncement($connection, $row->payload, $row->operation),
            AdverseEventReport::class => $this->syncAefi($connection, $row->payload, $row->operation),
            default => throw new \RuntimeException('Unsupported outbox model ['.$row->model_type.'].'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncChildProfile(string $connection, array $payload, string $operation): void
    {
        if ($operation === 'delete') {
            DB::connection($connection)->table('child_profiles')->where('sync_uuid', $payload['sync_uuid'])->delete();

            return;
        }

        $barangayId = DB::connection($connection)->table('barangays')->where('name', $payload['barangay_name'])->value('id');
        $creatorId = DB::connection($connection)->table('users')->where('email', $payload['creator_email'])->value('id');

        DB::connection($connection)->table('child_profiles')->updateOrInsert(
            ['sync_uuid' => $payload['sync_uuid']],
            [
                'barangay_id' => $barangayId,
                'created_by' => $creatorId,
                'first_name' => $payload['first_name'],
                'middle_name' => $payload['middle_name'],
                'last_name' => $payload['last_name'],
                'birthdate' => $payload['birthdate'],
                'sex' => $payload['sex'],
                'guardian_name' => $payload['guardian_name'],
                'guardian_contact' => $payload['guardian_contact'],
                'address' => $payload['address'],
                'vaccine_card_token' => $payload['vaccine_card_token'],
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncVaccinationRecord(string $connection, array $payload, string $operation): void
    {
        if ($operation === 'delete') {
            DB::connection($connection)->table('vaccination_records')->where('sync_uuid', $payload['sync_uuid'])->delete();

            return;
        }

        $childId = DB::connection($connection)->table('child_profiles')->where('sync_uuid', $payload['child_sync_uuid'])->value('id');
        $vaccineTypeId = DB::connection($connection)->table('vaccine_types')->where('code', $payload['vaccine_code'])->value('id');
        $recordedBy = DB::connection($connection)->table('users')->where('email', $payload['recorded_by_email'])->value('id');
        $submittedBy = DB::connection($connection)->table('users')->where('email', $payload['submitted_by_email'])->value('id');
        $verifiedBy = DB::connection($connection)->table('users')->where('email', $payload['verified_by_email'])->value('id');

        DB::connection($connection)->table('vaccination_records')->updateOrInsert(
            ['sync_uuid' => $payload['sync_uuid']],
            [
                'child_profile_id' => $childId,
                'vaccine_type_id' => $vaccineTypeId,
                'recorded_by' => $recordedBy,
                'submitted_by' => $submittedBy,
                'verified_by' => $verifiedBy,
                'dose_number' => $payload['dose_number'],
                'source' => $payload['source'],
                'verification_status' => $payload['verification_status'],
                'administered_at' => $payload['administered_at'],
                'verified_at' => $payload['verified_at'],
                'clinic_name' => $payload['clinic_name'],
                'clinic_location' => $payload['clinic_location'],
                'proof_path' => $payload['proof_path'],
                'proof_paths' => $payload['proof_paths'] ?? null,
                'client_submission_id' => $payload['client_submission_id'],
                'next_due_at' => $payload['next_due_at'],
                'suggested_vaccine' => $payload['suggested_vaccine'],
                'suggestion_note' => $payload['suggestion_note'],
                'remarks' => $payload['remarks'],
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncAnnouncement(string $connection, array $payload, string $operation): void
    {
        if ($operation === 'delete') {
            DB::connection($connection)->table('clinic_announcements')->where('sync_uuid', $payload['sync_uuid'])->delete();

            return;
        }

        $barangayId = DB::connection($connection)->table('barangays')->where('name', $payload['barangay_name'])->value('id');
        $creatorId = DB::connection($connection)->table('users')->where('email', $payload['creator_email'])->value('id');

        DB::connection($connection)->table('clinic_announcements')->updateOrInsert(
            ['sync_uuid' => $payload['sync_uuid']],
            [
                'barangay_id' => $barangayId,
                'created_by' => $creatorId,
                'title' => $payload['title'],
                'category' => $payload['category'],
                'audience' => $payload['audience'],
                'starts_on' => $payload['starts_on'],
                'ends_on' => $payload['ends_on'],
                'location' => $payload['location'],
                'message' => $payload['message'],
                'active' => $payload['active'],
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncAefi(string $connection, array $payload, string $operation): void
    {
        if ($operation === 'delete') {
            DB::connection($connection)->table('adverse_event_reports')->where('sync_uuid', $payload['sync_uuid'])->delete();

            return;
        }

        $childId = DB::connection($connection)->table('child_profiles')->where('sync_uuid', $payload['child_sync_uuid'])->value('id');
        $vaccinationRecordId = DB::connection($connection)->table('vaccination_records')->where('sync_uuid', $payload['vaccination_record_sync_uuid'])->value('id');
        $vaccineTypeId = DB::connection($connection)->table('vaccine_types')->where('code', $payload['vaccine_code'])->value('id');
        $reportedBy = DB::connection($connection)->table('users')->where('email', $payload['reported_by_email'])->value('id');

        DB::connection($connection)->table('adverse_event_reports')->updateOrInsert(
            ['sync_uuid' => $payload['sync_uuid']],
            [
                'child_profile_id' => $childId,
                'vaccination_record_id' => $vaccinationRecordId,
                'vaccine_type_id' => $vaccineTypeId,
                'reported_by' => $reportedBy,
                'event_date' => $payload['event_date'],
                'severity' => $payload['severity'],
                'outcome' => $payload['outcome'],
                'symptoms' => $payload['symptoms'],
                'notes' => $payload['notes'],
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
