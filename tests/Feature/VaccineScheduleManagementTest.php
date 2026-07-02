<?php

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\ChildVaccineSeriesVersion;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineSchedule;
use App\Models\VaccineScheduleVersion;
use App\Models\VaccineType;
use App\Services\ImmunizationSuggestionService;

test('admins can create and update vaccine schedule dose rules', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $vaccine = VaccineType::create(['code' => 'admin-test', 'name' => 'Admin Test Vaccine']);

    $this->actingAs($admin)
        ->post(route('vaccine-schedules.store'), [
            'vaccine_type_id' => $vaccine->id,
            'dose_number' => 1,
            'age_days' => 0,
            'age_weeks' => 6,
            'age_months' => 0,
            'age_years' => 0,
            'label' => '6 weeks',
            'indication' => 'catch_up_vaccination',
            'notes' => 'Initial dose',
            'active' => '1',
        ])
        ->assertRedirect(route('vaccine-schedules.index', absolute: false));

    $schedule = VaccineSchedule::where('vaccine_type_id', $vaccine->id)->firstOrFail();

    expect($schedule->label)->toBe('6 weeks')
        ->and($schedule->indication)->toBe('catch_up_vaccination')
        ->and($schedule->active)->toBeTrue();

    $this->actingAs($admin)
        ->put(route('vaccine-schedules.update', $schedule), [
            'vaccine_type_id' => $vaccine->id,
            'dose_number' => 1,
            'age_days' => 0,
            'age_weeks' => 8,
            'age_months' => 0,
            'age_years' => 0,
            'label' => '8 weeks',
            'indication' => 'routine_vaccination',
            'notes' => null,
        ])
        ->assertRedirect(route('vaccine-schedules.index', absolute: false));

    expect($schedule->fresh()->label)->toBe('8 weeks')
        ->and($schedule->fresh()->active)->toBeFalse();
});

test('started vaccine series keeps its assigned schedule version after a newer version becomes active', function () {
    $barangay = Barangay::create(['name' => 'Version Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $vaccine = VaccineType::create(['code' => 'ver-safe', 'name' => 'Version Safe Vaccine']);

    VaccineScheduleVersion::query()->update(['status' => 'archived']);

    $version2025 = VaccineScheduleVersion::create([
        'name' => 'PIDSP 2025',
        'version_code' => '2025.0',
        'effective_date' => '2025-01-01',
        'status' => 'archived',
    ]);

    $version2026 = VaccineScheduleVersion::create([
        'name' => 'PIDSP 2026',
        'version_code' => '2026.0',
        'effective_date' => '2026-01-01',
        'status' => 'active',
    ]);

    VaccineSchedule::create([
        'vaccine_type_id' => $vaccine->id,
        'vaccine_schedule_version_id' => $version2025->id,
        'dose_number' => 1,
        'age_months' => 1,
        'label' => '1 month',
        'indication' => 'routine_vaccination',
        'active' => true,
    ]);
    VaccineSchedule::create([
        'vaccine_type_id' => $vaccine->id,
        'vaccine_schedule_version_id' => $version2025->id,
        'dose_number' => 2,
        'age_months' => 2,
        'label' => '2 months',
        'indication' => 'routine_vaccination',
        'active' => true,
    ]);
    VaccineSchedule::create([
        'vaccine_type_id' => $vaccine->id,
        'vaccine_schedule_version_id' => $version2026->id,
        'dose_number' => 1,
        'age_months' => 1,
        'label' => '1 month',
        'indication' => 'routine_vaccination',
        'active' => true,
    ]);
    VaccineSchedule::create([
        'vaccine_type_id' => $vaccine->id,
        'vaccine_schedule_version_id' => $version2026->id,
        'dose_number' => 2,
        'age_months' => 3,
        'label' => '3 months',
        'indication' => 'routine_vaccination',
        'active' => true,
    ]);

    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Mia',
        'last_name' => 'Version',
        'birthdate' => '2025-01-10',
        'sex' => 'female',
        'guardian_name' => 'Parent Version',
    ]);

    VaccinationRecord::create([
        'child_profile_id' => $child->id,
        'vaccine_type_id' => $vaccine->id,
        'recorded_by' => $nurse->id,
        'dose_number' => 1,
        'source' => 'barangay_clinic',
        'verification_status' => 'verified',
        'verified_by' => $nurse->id,
        'verified_at' => now(),
        'administered_at' => '2025-02-10',
    ]);

    $suggestion = app(ImmunizationSuggestionService::class)->suggestNextDose($child);

    expect($suggestion['dose_number'])->toBe(2)
        ->and($suggestion['due_label'])->toBe('2 months')
        ->and($suggestion['suggested_schedule_version_id'])->toBe($version2025->id)
        ->and($suggestion['note'])->toContain('PIDSP 2025');

    expect(ChildVaccineSeriesVersion::where('child_profile_id', $child->id)
        ->where('vaccine_type_id', $vaccine->id)
        ->value('vaccine_schedule_version_id'))
        ->toBe($version2025->id);
});

test('new unstarted vaccine series follows the active schedule version', function () {
    $barangay = Barangay::create(['name' => 'Active Version Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $vaccine = VaccineType::create(['code' => 'ver-new', 'name' => 'Version New Vaccine']);

    VaccineScheduleVersion::query()->update(['status' => 'archived']);

    $version2025 = VaccineScheduleVersion::create([
        'name' => 'PIDSP 2025',
        'version_code' => '2025.1',
        'effective_date' => '2025-01-01',
        'status' => 'archived',
    ]);

    $version2026 = VaccineScheduleVersion::create([
        'name' => 'PIDSP 2026',
        'version_code' => '2026.1-test',
        'effective_date' => '2026-01-01',
        'status' => 'active',
    ]);

    foreach ([[$version2025, '2 months', 2], [$version2026, '3 months', 3]] as [$version, $label, $months]) {
        VaccineSchedule::create([
            'vaccine_type_id' => $vaccine->id,
            'vaccine_schedule_version_id' => $version->id,
            'dose_number' => 1,
            'age_months' => $months,
            'label' => $label,
            'indication' => 'routine_vaccination',
            'active' => true,
        ]);
    }

    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Noel',
        'last_name' => 'Future',
        'birthdate' => '2026-01-10',
        'sex' => 'male',
        'guardian_name' => 'Parent Future',
    ]);

    $suggestion = app(ImmunizationSuggestionService::class)->suggestNextDose($child);

    expect($suggestion['dose_number'])->toBe(1)
        ->and($suggestion['due_label'])->toBe('3 months')
        ->and($suggestion['suggested_schedule_version_id'])->toBe($version2026->id);
});
