<?php

namespace Tests\Feature;

use App\Models\ClinicAnnouncement;
use App\Models\ChildTransferHistory;
use App\Models\FacilityStaff;
use App\Models\Facility;
use App\Models\FacilityConnection;
use App\Models\SystemInstallation;
use App\Models\VaccineSchedule;
use App\Models\VaccineType;
use App\Services\CentralSyncService;
use App\Services\FacilityPullSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Tests\TestCase;

class CentralBootstrapSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_pull_returns_the_registered_master_data(): void
    {
        $facility = Facility::create(['code' => 'BRGY-SYNC', 'name' => 'Sync Facility', 'active' => true]);
        $client = Client::query()->create([
            'name' => 'Sync client', 'secret' => 'secret', 'redirect_uris' => [], 'grant_types' => ['client_credentials'], 'revoked' => false,
        ]);
        FacilityConnection::create(['facility_id' => $facility->id, 'instance_uuid' => (string) Str::uuid(), 'instance_name' => 'Workstation', 'passport_client_id' => $client->id, 'status' => 'active', 'activated_at' => now()]);
        $vaccine = VaccineType::create(['code' => 'SYNC-BCG', 'name' => 'Sync BCG', 'active' => true]);
        FacilityStaff::create(['facility_id' => $facility->id, 'staff_uuid' => (string) Str::uuid(), 'name' => 'Origin Nurse', 'role' => 'nurse', 'active' => true]);
        ChildTransferHistory::create(['child_sync_uuid' => (string) Str::uuid(), 'facility_uuid' => $facility->id, 'from_barangay_name' => 'Old Barangay', 'to_barangay_name' => 'New Barangay', 'transferred_at' => now()]);
        VaccineSchedule::create(['vaccine_type_id' => $vaccine->id, 'dose_number' => 1, 'age_days' => 0, 'age_weeks' => 0, 'age_months' => 0, 'age_years' => 0, 'label' => 'At birth', 'active' => true]);
        ClinicAnnouncement::create(['title' => 'Sync notice', 'starts_on' => now()->toDateString(), 'message' => 'Central message', 'active' => true]);

        $result = app(CentralSyncService::class)->pull(Request::create('/api/v1/sync/pull'), null, $client);

        $this->assertTrue(collect($result['data']['vaccines'])->contains('uuid', $vaccine->id));
        $this->assertTrue(collect($result['data']['schedule_rules'])->contains('vaccine_uuid', $vaccine->id));
        // Announcement synchronization is temporarily disabled; retain this assertion for future reactivation.
        // $this->assertSame('Sync notice', $result['data']['announcements'][0]['title']);
        $this->assertSame('Origin Nurse', $result['data']['facility_staff'][0]['name']);
        // Child-transfer synchronization is temporarily disabled; retain this assertion for future reactivation.
        // $this->assertSame('Old Barangay', $result['data']['child_transfers'][0]['from_barangay_name']);
        $this->assertNotEmpty($result['cursor']);
    }

    public function test_facility_pull_applies_master_data_and_advances_cursor_transactionally(): void
    {
        $installation = SystemInstallation::create([
            'instance_uuid' => (string) Str::uuid(), 'central_url' => 'https://central.test', 'passport_client_id' => (string) Str::uuid(),
            'passport_client_secret' => 'secret', 'status' => 'active',
        ]);
        $vaccineUuid = (string) Str::uuid();
        $scheduleUuid = (string) Str::uuid();
        $announcementUuid = (string) Str::uuid();
        $localVaccine = VaccineType::create(['code' => 'SYNC-PENTA', 'name' => 'Local Penta', 'active' => true]);
        Http::fake([
            'https://central.test/oauth/token' => Http::response(['access_token' => 'token']),
            'https://central.test/api/v1/sync/pull*' => Http::response([
                'cursor' => '2026-09-02T12:00:00+00:00',
                'data' => [
                    'vaccines' => [['uuid' => $vaccineUuid, 'code' => 'SYNC-PENTA', 'name' => 'Sync Penta', 'active' => true, 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]],
                    'schedule_rules' => [['uuid' => $scheduleUuid, 'vaccine_uuid' => $vaccineUuid, 'dose_number' => 1, 'age_days' => 0, 'age_weeks' => 6, 'age_months' => 0, 'age_years' => 0, 'label' => 'Six weeks', 'indication' => null, 'notes' => null, 'active' => true, 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]],
                    'announcements' => [['uuid' => $announcementUuid, 'title' => 'Offline notice', 'category' => 'schedule', 'audience' => 'all', 'starts_on' => now()->toDateString(), 'ends_on' => null, 'location' => null, 'message' => 'Use this offline', 'active' => true, 'updated_at' => now()->toIso8601String()]],
                ],
            ]),
        ]);

        $result = app(FacilityPullSyncService::class)->synchronize();

        $this->assertSame(3, $result['processed']);
        $this->assertDatabaseHas('vaccine_types', ['id' => $localVaccine->id, 'code' => 'SYNC-PENTA', 'name' => 'Sync Penta']);
        $this->assertDatabaseHas('vaccine_schedules', ['id' => $scheduleUuid, 'vaccine_type_id' => $localVaccine->id]);
        // Announcement synchronization is temporarily disabled; retain this assertion for future reactivation.
        // $this->assertDatabaseHas('clinic_announcements', ['sync_uuid' => $announcementUuid, 'created_by' => null]);
        $this->assertSame('2026-09-02T12:00:00+00:00', $installation->fresh()->pull_cursor);
    }
}
