<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\ChildTransferHistory;
use App\Models\ClinicAnnouncement;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FacilityPullSyncService
{
    /** @return array{processed: int, cursor: string} */
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

        DB::transaction(function () use ($payload, $installation): void {
            $this->applyStaff($payload['data']['facility_staff'] ?? [], $installation->facility_id);
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
            'processed' => collect($payload['data'] ?? [])->flatten(1)->count(),
            'cursor' => $payload['cursor'],
        ];
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
