<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FacilityActivationCode;
use App\Models\Barangay;
use App\Models\SystemInstallation;
use App\Models\User;
use App\Services\FacilityActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FacilityActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_issues_a_one_time_activation_code_and_passport_client(): void
    {
        $facility = Facility::create(['code' => 'BRGY-001', 'name' => 'Barangay One', 'active' => true]);
        $service = app(FacilityActivationService::class);

        $code = $service->issueCode($facility);
        $result = $service->activateCentral($code, (string) Str::uuid(), 'Facility workstation');

        $this->assertSame(32, strlen($code));
        $this->assertTrue(Hash::check($code, FacilityActivationCode::firstOrFail()->code_hash));
        $this->assertSame('BRGY-001', $result['facility_code']);
        $this->assertNotEmpty($result['passport_client_id']);
        $this->assertNotEmpty($result['passport_client_secret']);
        $this->assertDatabaseHas('facility_connections', [
            'facility_id' => $facility->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('oauth_clients', ['id' => $result['passport_client_id']]);
        $this->assertNotNull(FacilityActivationCode::firstOrFail()->used_at);
    }

    public function test_activation_code_cannot_be_used_twice(): void
    {
        $facility = Facility::create(['code' => 'BRGY-002', 'name' => 'Barangay Two', 'active' => true]);
        $service = app(FacilityActivationService::class);
        $code = $service->issueCode($facility);

        $service->activateCentral($code, (string) Str::uuid(), 'First workstation');

        $this->expectException(HttpException::class);
        $service->activateCentral($code, (string) Str::uuid(), 'Second workstation');
    }

    public function test_local_activation_stores_the_returned_secret_encrypted(): void
    {
        $installation = app(FacilityActivationService::class)->localInstallation();
        $this->assertSame('unactivated', $installation->status);

        Http::fake([
            'https://central.test/api/v1/facility/activate' => Http::response([
                'data' => [
                    'facility_uuid' => (string) Str::uuid(),
                    'facility_code' => 'BRGY-003',
                    'facility_name' => 'Barangay Three',
                    'passport_client_id' => (string) Str::uuid(),
                    'passport_client_secret' => 'secret-from-central',
                ],
            ]),
        ]);

        $installation = app(FacilityActivationService::class)->activateLocal('https://central.test', str_repeat('A', 32));

        $this->assertSame('active', $installation->status);
        $this->assertSame('secret-from-central', $installation->passport_client_secret);
        $this->assertSame('BRGY-003', $installation->facility_code);
        $this->assertDatabaseHas('system_installations', ['id' => $installation->id, 'status' => 'active']);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://central.test/api/v1/facility/activate');
    }

    public function test_expired_activation_code_is_rejected(): void
    {
        $facility = Facility::create(['code' => 'BRGY-004', 'name' => 'Barangay Four', 'active' => true]);
        $service = app(FacilityActivationService::class);
        $code = $service->issueCode($facility);
        FacilityActivationCode::firstOrFail()->update(['expires_at' => now()->subMinute()]);

        $this->expectException(HttpException::class);
        $service->activateCentral($code, (string) Str::uuid(), 'Facility workstation');
    }

    public function test_superadmin_can_revoke_facility_connections_and_passport_clients(): void
    {
        $facility = Facility::create(['code' => 'BRGY-005', 'name' => 'Barangay Five', 'active' => true]);
        $service = app(FacilityActivationService::class);
        $result = $service->activateCentral($service->issueCode($facility), (string) Str::uuid(), 'Facility workstation');

        $this->assertSame(1, $service->revokeFacilityConnections($facility));
        $this->assertDatabaseHas('facility_connections', ['facility_id' => $facility->id, 'status' => 'revoked']);
        $this->assertDatabaseHas('oauth_clients', ['id' => $result['passport_client_id'], 'revoked' => true]);
        $this->assertSame(0, $service->revokeFacilityConnections($facility));
    }

    public function test_activated_facility_can_create_its_first_barangay_admin(): void
    {
        $barangay = Barangay::create(['name' => 'Barangay One']);
        SystemInstallation::create(['instance_uuid' => (string) Str::uuid(), 'facility_name' => 'Barangay One', 'status' => 'active']);

        $response = $this->post(route('facility.setup.store'), [
            'name' => 'Barangay Administrator', 'email' => 'admin@example.com', 'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertDatabaseHas('users', ['email' => 'admin@example.com', 'role' => 'barangay_admin', 'barangay_id' => $barangay->id]);
    }

    public function test_setup_is_skipped_when_barangay_admin_already_exists(): void
    {
        $barangay = Barangay::create(['name' => 'Barangay Two']);
        SystemInstallation::create(['instance_uuid' => (string) Str::uuid(), 'facility_name' => 'Barangay Two', 'status' => 'active']);
        User::factory()->create(['role' => 'barangay_admin', 'roles' => ['barangay_admin'], 'barangay_id' => $barangay->id]);

        $this->get(route('facility.setup'))->assertRedirect(route('home'));
    }
}
