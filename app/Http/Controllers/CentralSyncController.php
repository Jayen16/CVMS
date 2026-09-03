<?php

namespace App\Http\Controllers;

use App\Services\CentralSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralSyncController extends Controller
{
    public function pull(Request $request, CentralSyncService $sync): JsonResponse
    {
        $request->validate(['cursor' => ['nullable', 'date']]);

        return response()->json($sync->pull($request, $request->string('cursor')->toString() ?: null, auth('api')->client()));
    }
}
