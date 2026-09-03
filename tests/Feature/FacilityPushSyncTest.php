<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\Facility;
use App\Models\FacilityConnection;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;
use App\Services\CentralPushSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Tests\TestCase;

class FacilityPushSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_accepts_facility_child_and_immunization_events_idempotently(): void
    {
        $region = Region::create(['name' => 'Region', 'code' => 'R-1']);
        $province = Province::create(['region_id' => $region->id, 'name' => 'Province', 'code' => 'P-1']);
        $municipality = Municipality::create(['province_id' => $province->id, 'name' => 'Municipality', 'code' => 'M-1']);
        $barangay = Barangay::create(['municipality_id' => $municipality->id, 'name' => 'Barangay']);
        $facility = Facility::create(['code' => 'PUSH-001', 'name' => 'Push Facility', 'active' => true]);
        $client = Client::query()->create(['name' => 'Push client', 'secret' => 'secret', 'redirect_uris' => [], 'grant_types' => ['client_credentials'], 'revoked' => false]);
        FacilityConnection::create(['facility_id' => $facility->id, 'instance_uuid' => (string) Str::uuid(), 'instance_name' => 'Workstation', 'passport_client_id' => $client->id, 'status' => 'active', 'activated_at' => now()]);
        $vaccine = VaccineType::create(['code' => 'PUSH-BCG', 'name' => 'Push BCG', 'active' => true]);
        $childUuid = (string) Str::uuid();
        $recordUuid = (string) Str::uuid();
        $events = [
            ['event_uuid' => (string) Str::uuid(), 'entity' => 'children', 'record_uuid' => $childUuid, 'operation' => 'updated', 'version' => 1, 'data' => ['sync_uuid' => $childUuid, 'barangay_name' => $barangay->name, 'municipality_code' => 'M-1', 'first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Santos', 'birthdate' => '2024-01-01', 'sex' => 'F', 'guardian_name' => 'Parent', 'guardian_contact' => null, 'address' => null, 'vaccine_card_token' => (string) Str::uuid(), 'registered_by_uuid' => (string) Str::uuid(), 'registered_by_name' => 'Nurse', 'registered_by_role' => 'nurse']],
            ['event_uuid' => (string) Str::uuid(), 'entity' => 'immunization_records', 'record_uuid' => $recordUuid, 'operation' => 'updated', 'version' => 1, 'data' => ['sync_uuid' => $recordUuid, 'child_sync_uuid' => $childUuid, 'vaccine_code' => 'PUSH-BCG', 'dose_number' => 1, 'source' => 'barangay_clinic', 'verification_status' => 'verified', 'administered_at' => '2024-01-01', 'verified_at' => null, 'clinic_name' => null, 'clinic_location' => null, 'proof_path' => null, 'proof_paths' => [], 'client_submission_id' => null, 'next_due_at' => null, 'suggested_vaccine' => null, 'suggested_schedule_version_id' => null, 'suggestion_note' => null, 'remarks' => null, 'administered_by_uuid' => (string) Str::uuid(), 'recorded_by_uuid' => (string) Str::uuid(), 'administered_by_name' => 'Nurse', 'recorded_by_name' => 'Nurse', 'recorded_by_role' => 'nurse']],
        ];

        $service = app(CentralPushSyncService::class);
        $result = $service->push($events, $client);
        $duplicateResult = $service->push($events, $client);
        $update = $events[0];
        $update['event_uuid'] = (string) Str::uuid();
        $update['version'] = 2;
        $update['data']['first_name'] = 'Ana Updated';
        $delete = $events[0];
        $delete['event_uuid'] = (string) Str::uuid();
        $delete['operation'] = 'deleted';
        $delete['version'] = 3;
        $delete['data'] = ['sync_uuid' => $childUuid, 'version' => 3];
        $service->push([$update, $delete], $client);

        $this->assertCount(2, $result['accepted']);
        $this->assertSame($result['accepted'], $duplicateResult['accepted']);
        $this->assertSame(1, ChildProfile::withoutGlobalScopes()->where('sync_uuid', $childUuid)->count());
        $this->assertSame('Ana Updated', ChildProfile::withoutGlobalScopes()->where('sync_uuid', $childUuid)->value('first_name'));
        $this->assertNotNull(ChildProfile::withoutGlobalScopes()->where('sync_uuid', $childUuid)->value('archived_at'));
        $this->assertSame(1, VaccinationRecord::withoutGlobalScopes()->where('sync_uuid', $recordUuid)->count());
        $this->assertDatabaseCount('sync_processed_events', 4);
    }
}
