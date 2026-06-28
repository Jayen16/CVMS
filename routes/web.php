<?php

use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdverseEventReportController;
use App\Http\Controllers\Api\ParentChildController;
use App\Http\Controllers\Api\OfflineVaccinationSyncController;
use App\Http\Controllers\ChildParentController;
use App\Http\Controllers\ChildProfileController;
use App\Http\Controllers\ChildVaccinationTimelineController;
use App\Http\Controllers\ClinicAnnouncementController;
use App\Http\Controllers\NurseController;
use App\Http\Controllers\VaccinationRecordController;
use App\Http\Controllers\VaccineScheduleController;
use App\Http\Controllers\VaccineCardController;
use App\Livewire\AefiReportsPage;
use App\Livewire\AnnouncementsPage;
use App\Livewire\DashboardPage;
use App\Livewire\DefaulterPage;
use App\Livewire\DuplicateChildrenPage;
use App\Livewire\VerificationQueuePage;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('vaccine-cards/{token}', [VaccineCardController::class, 'validateToken'])->name('vaccine-cards.validate');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardPage::class)->name('dashboard');
    Route::resource('children', ChildProfileController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::get('children/{child}/timeline', ChildVaccinationTimelineController::class)->name('children.timeline');
    Route::get('children/{child}/card', [VaccineCardController::class, 'show'])->name('children.card');
    Route::get('children/{child}/card/pdf', [VaccineCardController::class, 'pdf'])->name('children.card.pdf');
    Route::post('children/{child}/parents', [ChildParentController::class, 'store'])->name('children.parents.store');
    Route::post('children/{child}/parents/{parent}/setup-link', [ChildParentController::class, 'resendSetupLink'])->name('children.parents.setup-link');
    Route::delete('children/{child}/parents/{parent}', [ChildParentController::class, 'destroy'])->name('children.parents.destroy');
    Route::post('children/{child}/vaccinations', [VaccinationRecordController::class, 'store'])->name('children.vaccinations.store');
    Route::put('vaccinations/{record}', [VaccinationRecordController::class, 'update'])->name('vaccinations.update');
    Route::post('vaccinations/{record}/verify', [VaccinationRecordController::class, 'verify'])->name('vaccinations.verify');
    Route::post('vaccinations/{record}/reject', [VaccinationRecordController::class, 'reject'])->name('vaccinations.reject');
    Route::get('nurses', [NurseController::class, 'index'])->name('nurses.index');
    Route::post('nurses', [NurseController::class, 'store'])->name('nurses.store');
    Route::post('nurses/{nurse}/setup-link', [NurseController::class, 'resendSetupLink'])->name('nurses.setup-link');
    Route::post('nurses/{nurse}/toggle', [NurseController::class, 'toggle'])->name('nurses.toggle');
    Route::delete('nurses/{nurse}', [NurseController::class, 'destroy'])->name('nurses.destroy');
    Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
    Route::get('reports/pdf', [AdminReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('announcements', AnnouncementsPage::class)->name('announcements.index');
    Route::post('announcements', [ClinicAnnouncementController::class, 'store'])->name('announcements.store');
    Route::post('announcements/{announcement}/toggle', [ClinicAnnouncementController::class, 'toggle'])->name('announcements.toggle');
    Route::delete('announcements/{announcement}', [ClinicAnnouncementController::class, 'destroy'])->name('announcements.destroy');
    Route::get('verification-queue', VerificationQueuePage::class)->name('verification-queue.index');
    Route::get('defaulters', DefaulterPage::class)->name('defaulters.index');
    Route::get('duplicates', DuplicateChildrenPage::class)->name('duplicates.index');
    Route::get('aefi-reports', AefiReportsPage::class)->name('aefi-reports.index');
    Route::post('children/{child}/aefi-reports', [AdverseEventReportController::class, 'store'])->name('children.aefi-reports.store');
    Route::resource('vaccine-schedules', VaccineScheduleController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::post('vaccine-schedules/{vaccineSchedule}/toggle', [VaccineScheduleController::class, 'toggle'])->name('vaccine-schedules.toggle');
    Route::post('vaccine-types/{vaccineType}/toggle', [VaccineScheduleController::class, 'toggleVaccine'])->name('vaccine-types.toggle');

    Route::prefix('api/parent')->name('api.parent.')->group(function () {
        Route::get('children', [ParentChildController::class, 'index'])->name('children.index');
        Route::get('children/{child}/vaccinations', [ParentChildController::class, 'vaccinations'])->name('children.vaccinations');
        Route::post('children/{child}/vaccinations', [ParentChildController::class, 'storeVaccination'])->name('children.vaccinations.store');
        Route::post('children/{child}/offline-sync', [OfflineVaccinationSyncController::class, 'store'])->name('children.offline-sync');
        Route::get('vaccines', [ParentChildController::class, 'vaccines'])->name('vaccines.index');
    });
});

require __DIR__.'/settings.php';
