<?php

namespace App\Http\Controllers;

use App\Services\CentralPushSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralPushSyncController extends Controller
{
    public function push(Request $request, CentralPushSyncService $sync): JsonResponse
    {
        $data = $request->validate(['events' => ['required', 'array', 'max:50'], 'events.*.event_uuid' => ['required', 'uuid'], 'events.*.entity' => ['required', 'string'], 'events.*.record_uuid' => ['required', 'uuid'], 'events.*.operation' => ['required', 'in:created,updated,deleted'], 'events.*.version' => ['required', 'integer', 'min:1'], 'events.*.data' => ['required', 'array']]);

        return response()->json($sync->push($data['events'], auth('api')->client()));
    }
}
