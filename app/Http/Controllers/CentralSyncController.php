<?php

namespace App\Http\Controllers;

use App\Services\CentralSyncService;
use App\Models\FacilityConnection;
use App\Models\VaccinationRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CentralSyncController extends Controller
{
    public function pull(Request $request, CentralSyncService $sync): JsonResponse
    {
        $request->validate(['cursor' => ['nullable', 'date']]);

        return response()->json($sync->pull($request, $request->string('cursor')->toString() ?: null, auth('api')->client()));
    }

    public function proof(string $record, int $proofIndex): Response
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
        $proofDisk = Storage::disk(config('filesystems.proof_disk', 'public'));
        abort_unless($proofDisk->exists($proofPath), 404);

        if (config('filesystems.disks.'.config('filesystems.proof_disk', 'public').'.driver') === 's3') {
            return redirect()->away($proofDisk->temporaryUrl($proofPath, now()->addMinutes(10)));
        }

        return $proofDisk->response($proofPath);
    }

    public function uploadProof(Request $request, string $record, int $proofIndex): JsonResponse
    {
        $client = auth('api')->client();
        $connection = $client
            ? FacilityConnection::query()->where('passport_client_id', $client->getKey())->where('status', 'active')->first()
            : null;

        abort_unless($connection, 403, 'Facility connection is not active.');
        $request->validate(['file' => ['required', 'file', 'image', 'max:20480']]);

        $vaccination = VaccinationRecord::withoutGlobalScopes()
            ->where('sync_uuid', $record)
            ->where('facility_uuid', $connection->facility_id)
            ->firstOrFail();
        $proofPath = $vaccination->proofPaths()[$proofIndex - 1] ?? null;

        abort_if($proofPath === null, 404);
        abort_unless(str_starts_with($proofPath, 'vaccination-proofs/'), 422, 'Invalid proof path.');

        $proofDisk = Storage::disk(config('filesystems.proof_disk', 'public'));
        abort_unless($proofDisk->put($proofPath, $request->file('file')->get()), 500, 'Unable to save vaccination proof.');

        return response()->json(['stored' => true]);
    }
}
