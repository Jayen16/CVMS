<?php

use App\Http\Controllers\CentralPushSyncController;
use App\Http\Controllers\CentralSyncController;
use App\Http\Controllers\FacilityActivationController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CheckToken;

if (config('system.instance_type') === 'central') {
    Route::middleware('throttle:5,1')->post('v1/facility/activate', [FacilityActivationController::class, 'activateApi'])->name('api.v1.facility.activate');
    Route::middleware(['auth:api', CheckToken::using('sync:pull')])->get('v1/sync/pull', [CentralSyncController::class, 'pull'])->name('api.v1.sync.pull');
    Route::middleware(['auth:api', CheckToken::using('sync:push')])->post('v1/sync/push', [CentralPushSyncController::class, 'push'])->name('api.v1.sync.push');
}
