<?php

namespace App\Services;

use App\Models\Barangay;
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
            $this->applyVaccines($payload['data']['vaccines'] ?? []);
            $this->applySchedules($payload['data']['schedule_rules'] ?? []);
            $this->applyAnnouncements($payload['data']['announcements'] ?? []);
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

    /** @param array<int, array<string, mixed>> $records */
    private function applyVaccines(array $records): void
    {
        foreach ($records as $record) {
            DB::table('vaccine_types')->updateOrInsert(['id' => $record['uuid']], [
                'code' => $record['code'], 'name' => $record['name'], 'active' => $record['active'],
                'created_at' => $record['created_at'], 'updated_at' => $record['updated_at'],
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $records */
    private function applySchedules(array $records): void
    {
        foreach ($records as $record) {
            DB::table('vaccine_schedules')->updateOrInsert(['id' => $record['uuid']], [
                'vaccine_type_id' => $record['vaccine_uuid'], 'dose_number' => $record['dose_number'],
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
