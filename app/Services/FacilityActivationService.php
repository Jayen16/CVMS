<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\FacilityActivationCode;
use App\Models\FacilityConnection;
use App\Models\SystemInstallation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

class FacilityActivationService
{
    public function localInstallation(): SystemInstallation
    {
        return SystemInstallation::query()->first() ?? SystemInstallation::create([
            'instance_uuid' => (string) Str::uuid(),
            'status' => 'unactivated',
        ]);
    }

    public function issueCode(Facility $facility): string
    {
        $code = strtoupper(Str::random(32));
        $facility->activationCodes()->create([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addHours(config('system.activation_code_ttl_hours', 24)),
        ]);

        return $code;
    }

    /** @return array<string, string> */
    public function activateCentral(string $code, string $instanceUuid, string $instanceName): array
    {
        return DB::transaction(function () use ($code, $instanceUuid, $instanceName): array {
            $activation = FacilityActivationCode::query()
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->with('facility')
                ->get()
                ->first(fn (FacilityActivationCode $candidate): bool => Hash::check($code, $candidate->code_hash));

            abort_unless($activation?->facility?->active, 422, 'Invalid or expired activation code.');

            $client = app(ClientRepository::class)->createClientCredentialsGrantClient(
                $activation->facility->name.' ('.$instanceUuid.')',
            );

            $activation->update(['used_at' => now()]);
            FacilityConnection::updateOrCreate(
                ['instance_uuid' => $instanceUuid],
                ['facility_id' => $activation->facility_id, 'instance_name' => $instanceName, 'passport_client_id' => $client->getKey(), 'status' => 'active', 'activated_at' => now(), 'revoked_at' => null],
            );

            return [
                'facility_uuid' => (string) $activation->facility_id,
                'facility_code' => $activation->facility->code,
                'facility_name' => $activation->facility->name,
                'passport_client_id' => (string) $client->getKey(),
                'passport_client_secret' => (string) $client->plainSecret,
            ];
        });
    }

    public function activateLocal(string $centralUrl, string $code): SystemInstallation
    {
        $installation = $this->localInstallation();
        $response = Http::acceptJson()->timeout(15)->post(rtrim($centralUrl, '/').'/api/v1/facility/activate', [
            'activation_code' => $code,
            'instance_uuid' => $installation->instance_uuid,
            'instance_name' => config('rhu.short_name'),
        ]);
        $response->throw();
        $data = $response->json('data');

        $installation->update([
            'facility_id' => $data['facility_uuid'], 'facility_code' => $data['facility_code'], 'facility_name' => $data['facility_name'],
            'central_url' => rtrim($centralUrl, '/'), 'passport_client_id' => $data['passport_client_id'],
            'passport_client_secret' => $data['passport_client_secret'], 'status' => 'active', 'activated_at' => now(),
        ]);

        return $installation->fresh();
    }

    public function revokeFacilityConnections(Facility $facility): int
    {
        return DB::transaction(function () use ($facility): int {
            $connections = $facility->connections()->where('status', 'active')->get();
            $clientRepository = app(ClientRepository::class);

            foreach ($connections as $connection) {
                if ($client = Client::query()->find($connection->passport_client_id)) {
                    $clientRepository->delete($client);
                }

                $connection->update(['status' => 'revoked', 'revoked_at' => now()]);
            }

            return $connections->count();
        });
    }
}
