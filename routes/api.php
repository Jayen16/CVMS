<?php

use App\Http\Controllers\CentralPushSyncController;
use App\Http\Controllers\CentralSyncController;
use App\Http\Controllers\FacilityActivationController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CheckToken;

if (config('system.instance_type') === 'central') {
    Route::middleware('throttle:5,1')->post('v1/facility/activate', [FacilityActivationController::class, 'activateApi'])->name('api.v1.facility.activate');
    // These endpoints use Passport's client-credentials grant. Client tokens
    // do not have a user, so auth:api would reject them as unauthenticated.
    // CheckToken validates the bearer token and its required scope; the
    // controllers resolve the authenticated Passport client from the token.
    Route::middleware(CheckToken::using('sync:pull'))->get('v1/sync/pull', [CentralSyncController::class, 'pull'])->name('api.v1.sync.pull');
    Route::middleware(CheckToken::using('sync:push'))->post('v1/sync/push', [CentralPushSyncController::class, 'push'])->name('api.v1.sync.push');
}
