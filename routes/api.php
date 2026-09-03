<?php

use App\Http\Controllers\FacilityActivationController;
use Illuminate\Support\Facades\Route;

if (config('system.instance_type') === 'central') {
    Route::middleware('throttle:5,1')->post('v1/facility/activate', [FacilityActivationController::class, 'activateApi'])->name('api.v1.facility.activate');
}
