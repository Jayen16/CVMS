<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\ChildTransferHistory;
use App\Models\ClinicAnnouncement;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\Region;
use App\Models\SyncReceivedItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;

class FacilityPullSyncService
{
    /** @return array{processed: int, cursor: string, batch_uuid: string, received: int} */
    public function synchronize(): array
    {
        $installation = app(FacilityActivationService::class)->localInstallation();
        abort_unless($installation->status === 'active', 422, 'This facility is not activated.');

        $centralUrl = rtrim((string) $installation->central_url, '/');
        $token = Http::asForm()->timeout(15)->withBasicAuth($installation->passport_client_id, $installation->passport_client_secret)->post($centralUrl.'/oauth/token', [
            'grant_type' => 'client_credentials',
            'scope' => 'sync:pull',
        ])->throw()->json('access_token');

        $response = Http::acceptJson()->withToken($token)->timeout(30)->get($centralUrl.'/api/v1/sync/pull', [
            'cursor' => $installation->pull_cursor,
        ])->throw();
        $payload = $response->json();
        $parentRecords = $payload['data']['parent_vaccination_records'] ?? [];
        $previouslyReceivedRecords = VaccinationRecord::query()
            ->whereIn('sync_uuid', SyncReceivedItem::query()
                ->where('entity', 'parent_vaccination_records')
                ->pluck('record_uuid'))
            ->where(function ($query): void {
                $query->whereNotNull('proof_path')->orWhereNotNull('proof_paths');
            })
            ->get(['sync_uuid', 'proof_path', 'proof_paths'])
            ->map(fn (VaccinationRecord $record): array => [
                'uuid' => $record->sync_uuid,
                'proof_path' => $record->proof_path,
                'proof_paths' => $record->proof_paths,
            ])
            ->all();
        // The current pull can contain the same pending records as the
        // retry list below. Keep one proof-download attempt per record per
        // sync while retaining retries for records outside the current pull.
        $proofRecords = collect(array_merge($parentRecords, $previouslyReceivedRecords))
            ->filter(fn (mixed $record): bool => is_array($record) && filled($record['uuid'] ?? null))
            ->keyBy('uuid')
            ->values()
            ->all();

        $this->downloadParentProofs($proofRecords, $centralUrl, $token);
        $batchUuid = (string) Str::uuid();

        $received = 0;

        DB::transaction(function () use ($payload, $installation, $batchUuid, &$received): void {
            $this->applyStaff($payload['data']['facility_staff'] ?? [], $installation->facility_id);
            $this->applyParentAccounts($payload['data']['parent_accounts'] ?? []);
            $this->applyParentVaccinationRecords($payload['data']['parent_vaccination_records'] ?? []);
            $received = $this->recordReceivedItems($payload['data'] ?? [], $batchUuid);
            /*
             * Temporarily disabled; retain for future reactivation.
             * $this->applyChildTransfers($payload['data']['child_transfers'] ?? [], $installation->facility_id);
             */
            $vaccineIds = $this->applyVaccines($payload['data']['vaccines'] ?? []);
            $this->applySchedules($payload['data']['schedule_rules'] ?? [], $vaccineIds);
            /*
             * Temporarily disabled; retain for future reactivation.
             * $this->applyAnnouncements($payload['data']['announcements'] ?? []);
             */
            $this->applyParentChangeRequests($payload['data']['parent_change_requests'] ?? []);
            $installation->update([
                'pull_cursor' => $payload['cursor'],
                'last_synchronized_at' => now(),
            ]);
        });

        return [
            'processed' => $received,
            'cursor' => $payload['cursor'],
            'batch_uuid' => $batchUuid,
            'received' => $received,
        ];
    }

    /** @param array<int, array<string, mixed>> $records */
    private function downloadParentProofs(array $records, string $centralUrl, string $token): void
    {
        // Central may use private S3, but the facility always keeps its own
        // offline copy on the local public disk.
        $proofDisk = Storage::disk('public');
        $proofDisk->makeDirectory('vaccination-proofs');

        foreach ($records as $record) {
            $paths = is_array($record['proof_paths'] ?? null) ? $record['proof_paths'] : [];

            if (filled($record['proof_path'] ?? null) && ! in_array($record['proof_path'], $paths, true)) {
                $paths[] = $record['proof_path'];
            }

            foreach (array_values($paths) as $index => $path) {
                if (! is_string($path) || ! Str::startsWith($path, 'vaccination-proofs/')) {
                    continue;
                }

                if ($proofDisk->exists($path)) {
                    continue;
                }

                try {
                    $contents = Http::withToken($token)
                        ->withOptions(['allow_redirects' => true])
                        ->timeout(30)
                        ->get($centralUrl.'/api/v1/sync/proofs/'.rawurlencode((string) ($record['uuid'] ?? '')).'/'.($index + 1))
                        ->throw()
                        ->body();
                } catch (RequestException $exception) {
                    // A stale path or a proof removed from Central must not
                    // prevent the rest of the pull batch from being applied.
                    // Keep non-404 failures fatal so authentication and
                    // connectivity problems remain visible to the operator.
                    if ($exception->response?->status() !== 404) {
                        throw $exception;
                    }

                    Log::warning('Central vaccination proof was not found; continuing sync.', [
                        'record_uuid' => $record['uuid'] ?? null,
                        'proof_index' => $index + 1,
                        'proof_path' => $path,
                    ]);

                    continue;
                }

                abort_unless($proofDisk->put($path, $contents), 500, 'Unable to save synchronized vaccination proof.');
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function recordReceivedItems(array $data, string $batchUuid): int
    {
        $receivedAt = now();
        $received = 0;

        foreach ($data as $entity => $records) {
            if (! is_array($records)) {
                continue;
            }

            foreach ($records as $record) {
                if (! is_array($record) || blank($record['uuid'] ?? null)) {
                    continue;
                }

                $existing = SyncReceivedItem::query()
                    ->where('entity', $entity)
                    ->where('record_uuid', $record['uuid'])
                    ->first();

                if ($existing && $existing->payload == $record) {
                    continue;
                }

                SyncReceivedItem::query()->updateOrCreate(
                    ['entity' => $entity, 'record_uuid' => $record['uuid']],
                    [
                        'operation' => 'received',
                        'payload' => $record,
                        'last_received_batch_uuid' => $batchUuid,
                        'first_received_at' => $existing?->first_received_at ?? $receivedAt,
                        'last_received_at' => $receivedAt,
                    ]
                );
                $received++;
            }
        }

        return $received;
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<string, string> Central vaccine UUID to local vaccine ID.
     */
    private function applyVaccines(array $records): array
    {
        $vaccineIds = [];

        foreach ($records as $record) {
            $existing = DB::table('vaccine_types')->where('code', $record['code'])->first();
            $localId = $existing?->id ?? $record['uuid'];

            if ($existing) {
                DB::table('vaccine_types')->where('id', $localId)->update([
                    'name' => $record['name'], 'active' => $record['active'], 'updated_at' => $record['updated_at'],
                ]);
            } else {
                DB::table('vaccine_types')->insert([
                    'id' => $localId, 'code' => $record['code'], 'name' => $record['name'], 'active' => $record['active'],
                    'created_at' => $record['created_at'], 'updated_at' => $record['updated_at'],
                ]);
            }

            $vaccineIds[$record['uuid']] = $localId;
        }

        return $vaccineIds;
    }

    private function applyStaff(array $records, ?string $facilityId): void
    {
        foreach ($records as $record) {
            DB::table('facility_staff')->updateOrInsert(['facility_id' => $facilityId, 'staff_uuid' => $record['uuid']], [
                'id' => $record['uuid'], 'name' => $record['name'], 'role' => $record['role'], 'active' => $record['active'] ?? true,
                'last_seen_at' => $record['last_seen_at'] ?? null, 'created_at' => now(), 'updated_at' => $record['updated_at'] ?? now(),
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $records */
    private function applyParentAccounts(array $records): void
    {
        foreach ($records as $record) {
            $parent = User::query()
                ->where(function ($query) use ($record): void {
                    $query->whereKey($record['uuid'])
                        ->when(filled($record['email'] ?? null), fn ($query) => $query->orWhere('email', $record['email']))
                        ->when(filled($record['phone'] ?? null), fn ($query) => $query->orWhere('phone', $record['phone']));
                })
                ->where(function ($query): void {
                    $query->where('role', 'parent')->orWhereJsonContains('roles', 'parent');
                })
                ->first();

            $parent?->forceFill([
                'invitation_accepted_at' => $record['invitation_accepted_at'] ?? null,
                'is_active' => (bool) ($record['active'] ?? true),
            ])->saveQuietly();
        }
    }

    /** @param array<int, array<string, mixed>> $records */
    private function applyParentVaccinationRecords(array $records): void
    {
        foreach ($records as $record) {
            $childId = DB::table('child_profiles')->where('sync_uuid', $record['child_uuid'])->value('id');
            $vaccineId = DB::table('vaccine_types')->where('code', $record['vaccine_code'])->value('id');

            if (! $childId || ! $vaccineId) {
                continue;
            }

            $parent = User::query()
                ->where(function ($query) use ($record): void {
                    $query->whereKey($record['submitter_uuid'])
                        ->when(filled($record['submitter_email'] ?? null), fn ($query) => $query->orWhere('email', $record['submitter_email']))
                        ->when(filled($record['submitter_phone'] ?? null), fn ($query) => $query->orWhere('phone', $record['submitter_phone']));
                })
                ->where(function ($query): void {
                    $query->where('role', 'parent')->orWhereJsonContains('roles', 'parent');
                })
                ->first();

            if (! $parent) {
                continue;
            }

            $local = VaccinationRecord::withoutGlobalScopes()->where('sync_uuid', $record['uuid'])->first() ?? new VaccinationRecord;

            // A nurse can verify a record locally while the original pending
            // copy is still waiting to be pushed to Central. Do not let that
            // older pending pull undo the local decision. The sync version is
            // incremented whenever the local record is changed, including
            // verification/rejection.
            if ($local->exists && (int) ($local->sync_version ?: 1) >= (int) ($record['version'] ?? 1)) {
                continue;
            }

            $local->forceFill([
                'id' => $local->id ?: $record['uuid'],
                'sync_uuid' => $record['uuid'],
                'child_profile_id' => $childId,
                'vaccine_type_id' => $vaccineId,
                'recorded_by' => $parent->id,
                'submitted_by' => $parent->id,
                'verified_by' => null,
                'verified_at' => $record['verified_at'] ?? null,
                'dose_number' => $record['dose_number'],
                'source' => $record['source'],
                'verification_status' => $record['verification_status'],
                'administered_at' => $record['administered_at'],
                'clinic_name' => $record['clinic_name'] ?? null,
                'clinic_location' => $record['clinic_location'] ?? null,
                'proof_path' => $record['proof_path'] ?? null,
                'proof_paths' => $record['proof_paths'] ?? null,
                'client_submission_id' => $record['client_submission_id'] ?? null,
                'next_due_at' => $record['next_due_at'] ?? null,
                'suggested_vaccine' => $record['suggested_vaccine'] ?? null,
                'suggestion_note' => $record['suggestion_note'] ?? null,
                'remarks' => $record['remarks'] ?? null,
                'sync_version' => $record['version'] ?? 1,
            ])->saveQuietly();

            if ($local->verification_status === 'pending') {
                app(InAppNotificationService::class)->vaccinationSubmitted($local->fresh());
            }
        }
    }

    private function applyChildTransfers(array $records, ?string $facilityId): void
    {
        foreach ($records as $record) {
            ChildTransferHistory::query()->updateOrCreate(['id' => $record['uuid']], [
                'child_sync_uuid' => $record['child_uuid'], 'facility_uuid' => $facilityId,
                'from_barangay_name' => $record['from_barangay_name'], 'to_barangay_name' => $record['to_barangay_name'],
                'municipality_code' => $record['municipality_code'] ?? null, 'transferred_by_uuid' => $record['transferred_by_uuid'] ?? null,
                'transferred_by_name' => $record['transferred_by_name'] ?? null, 'transferred_by_role' => $record['transferred_by_role'] ?? null,
                'transferred_at' => $record['transferred_at'], 'reason' => $record['reason'] ?? null, 'sync_version' => $record['version'] ?? 1,
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @param array<string, string> $vaccineIds Central vaccine UUID to local vaccine ID.
     */
    private function applySchedules(array $records, array $vaccineIds): void
    {
        foreach ($records as $record) {
            $localVaccineId = $vaccineIds[$record['vaccine_uuid']] ?? null;

            if ($localVaccineId === null) {
                continue;
            }

            DB::table('vaccine_schedules')->updateOrInsert(['id' => $record['uuid']], [
                'vaccine_type_id' => $localVaccineId, 'dose_number' => $record['dose_number'],
                'age_days' => $record['age_days'], 'age_weeks' => $record['age_weeks'], 'age_months' => $record['age_months'], 'age_years' => $record['age_years'],
                'label' => $record['label'], 'indication' => $record['indication'] ?? 'routine_vaccination', 'notes' => $record['notes'], 'active' => $record['active'],
                'created_at' => $record['created_at'], 'updated_at' => $record['updated_at'],
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $records */
    private function applyAnnouncements(array $records): void
    {
        foreach ($records as $record) {
            ClinicAnnouncement::query()->updateOrCreate(['sync_uuid' => $record['uuid']], [
                'region_id' => $this->locationId(Region::class, $record['region_code'] ?? null),
                'province_id' => $this->locationId(Province::class, $record['province_code'] ?? null),
                'municipality_id' => $this->locationId(Municipality::class, $record['municipality_code'] ?? null),
                'barangay_id' => $this->barangayId($record['barangay_name'] ?? null, $record['municipality_code'] ?? null),
                'created_by' => null,
                'title' => $record['title'], 'category' => $record['category'], 'audience' => $record['audience'],
                'starts_on' => $record['starts_on'], 'ends_on' => $record['ends_on'], 'location' => $record['location'],
                'message' => $record['message'], 'active' => $record['active'], 'updated_at' => $record['updated_at'],
            ]);
        }
    }

    private function applyParentChangeRequests(array $records): void
    {
        foreach ($records as $record) {
            DB::table('parent_change_requests')->updateOrInsert(['request_uuid' => $record['uuid']], [
                'id' => $record['uuid'], 'facility_id' => app(FacilityActivationService::class)->localInstallation()->facility_id,
                'child_uuid' => $record['child_uuid'], 'parent_uuid' => $record['parent_uuid'], 'request_type' => $record['request_type'],
                'requested_data' => json_encode($record['requested_data'] ?? []), 'status' => $record['status'], 'reviewer_name' => $record['reviewer_name'] ?? null,
                'reviewer_note' => $record['reviewer_note'] ?? null, 'created_at' => now(), 'updated_at' => $record['updated_at'] ?? now(),
            ]);
        }
    }

    private function locationId(string $model, ?string $code): ?string
    {
        return filled($code) ? $model::query()->where('code', $code)->value('id') : null;
    }

    private function barangayId(?string $name, ?string $municipalityCode): ?string
    {
        if (blank($name)) {
            return null;
        }

        return Barangay::query()->where('name', $name)->when($municipalityCode, fn ($query) => $query->whereHas('municipalityRelation', fn ($municipality) => $municipality->where('code', $municipalityCode)))->value('id');
    }
}
