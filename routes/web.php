<?php

use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\Api\ParentChildController;
use App\Http\Controllers\ChildParentController;
use App\Http\Controllers\ChildProfileController;
use App\Http\Controllers\ChildVaccinationTimelineController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NurseController;
use App\Http\Controllers\VaccinationRecordController;
use App\Http\Controllers\VaccineScheduleController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::resource('children', ChildProfileController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('children/{child}/timeline', ChildVaccinationTimelineController::class)->name('children.timeline');
    Route::post('children/{child}/parents', [ChildParentController::class, 'store'])->name('children.parents.store');
    Route::post('children/{child}/vaccinations', [VaccinationRecordController::class, 'store'])->name('children.vaccinations.store');
    Route::post('vaccinations/{record}/verify', [VaccinationRecordController::class, 'verify'])->name('vaccinations.verify');
    Route::post('vaccinations/{record}/reject', [VaccinationRecordController::class, 'reject'])->name('vaccinations.reject');
    Route::get('nurses', [NurseController::class, 'index'])->name('nurses.index');
    Route::post('nurses', [NurseController::class, 'store'])->name('nurses.store');
    Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
    Route::get('reports/pdf', [AdminReportController::class, 'pdf'])->name('reports.pdf');
    Route::resource('vaccine-schedules', VaccineScheduleController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::post('vaccine-schedules/{vaccineSchedule}/toggle', [VaccineScheduleController::class, 'toggle'])->name('vaccine-schedules.toggle');

    Route::prefix('api/parent')->name('api.parent.')->group(function () {
        Route::get('children', [ParentChildController::class, 'index'])->name('children.index');
        Route::get('children/{child}/vaccinations', [ParentChildController::class, 'vaccinations'])->name('children.vaccinations');
        Route::post('children/{child}/vaccinations', [ParentChildController::class, 'storeVaccination'])->name('children.vaccinations.store');
        Route::get('vaccines', [ParentChildController::class, 'vaccines'])->name('vaccines.index');
    });
});

require __DIR__.'/settings.php';
