<?php

namespace App\Http\Controllers;

use App\Services\CentralSyncService;
use App\Models\FacilityConnection;
use App\Models\VaccinationRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CentralSyncController extends Controller
{
    public function pull(Request $request, CentralSyncService $sync): JsonResponse
    {
        $request->validate(['cursor' => ['nullable', 'date']]);

        return response()->json($sync->pull($request, $request->string('cursor')->toString() ?: null, auth('api')->client()));
    }

    public function proof(string $record, int $proofIndex): StreamedResponse
    {
        $client = auth('api')->client();
        $connection = $client
            ? FacilityConnection::query()->where('passport_client_id', $client->getKey())->where('status', 'active')->first()
            : null;

        abort_unless($connection, 403, 'Facility connection is not active.');

        $vaccination = VaccinationRecord::withoutGlobalScopes()
            ->where('sync_uuid', $record)
            ->where('facility_uuid', $connection->facility_id)
            ->firstOrFail();
        $proofPath = $vaccination->proofPaths()[$proofIndex - 1] ?? null;

        abort_if($proofPath === null, 404);
        abort_unless(Storage::disk('public')->exists($proofPath), 404);

        return Storage::disk('public')->response($proofPath);
    }
}
