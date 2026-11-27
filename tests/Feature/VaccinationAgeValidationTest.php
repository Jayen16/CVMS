<?php

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\ChildVaccineSeriesVersion;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineSchedule;
use App\Models\VaccineScheduleVersion;
use App\Models\VaccineType;
use App\Services\VaccinationSubmissionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 11, 28)->startOfDay());
    $barangay = Barangay::create(['name' => 'Age Validation Barangay']);
    $this->nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $this->child = ChildProfile::create([
        'barangay_id' => $barangay->id, 'created_by' => $this->nurse->id,
        'first_name' => 'Age', 'last_name' => 'Validation',
        'birthdate' => '2026-01-01', 'sex' => 'female', 'guardian_name' => 'Guardian',
    ]);
    $this->vaccine = VaccineType::create(['name' => 'Age Validation Vaccine', 'code' => 'AGE-TEST', 'active' => true]);
    VaccineScheduleVersion::query()->update(['status' => 'draft']);
});

test('vaccination dates before birth or in the future are rejected', function (string $date) {
    try {
        app(VaccinationSubmissionService::class)->validate($this->nurse, $this->child, [
            'vaccine_type_id' => $this->vaccine->id, 'dose_number' => 1, 'administered_at' => $date,
        ]);
        $this->fail('An invalid vaccination date was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('administered_at');
    }
})->with(['2025-12-31', '2026-11-29']);

test('historical vaccination dates on or after birth are accepted without a configured age rule', function (string $date) {
    $validated = app(VaccinationSubmissionService::class)->validate($this->nurse, $this->child, [
        'vaccine_type_id' => $this->vaccine->id, 'dose_number' => 1, 'administered_at' => $date,
    ]);
    expect($validated['administered_at'])->toBe($date);
})->with(['2026-01-01', '2026-11-28']);

test('age checks use the vaccination date and allow the exact scheduled date and late history', function (string $date, bool $allowed) {
    $version = VaccineScheduleVersion::create([
        'name' => 'Age Test', 'version_code' => 'age-test', 'status' => 'active', 'effective_date' => '2025-01-01',
    ]);
    VaccineSchedule::create([
        'vaccine_type_id' => $this->vaccine->id, 'vaccine_schedule_version_id' => $version->id,
        'dose_number' => 1, 'age_weeks' => 6, 'label' => 'Six weeks', 'active' => true,
    ]);
    $input = ['vaccine_type_id' => $this->vaccine->id, 'dose_number' => 1, 'administered_at' => $date];
    if ($allowed) {
        expect(app(VaccinationSubmissionService::class)->validate($this->nurse, $this->child, $input)['administered_at'])->toBe($date);
    } else {
        try {
            app(VaccinationSubmissionService::class)->validate($this->nurse, $this->child, $input);
            $this->fail('An early vaccination was accepted.');
        } catch (ValidationException $exception) {
            expect($exception->errors()['administered_at'][0])->toContain('Feb 12, 2026');
        }
    }
    expect(ChildVaccineSeriesVersion::where('child_profile_id', $this->child->id)->exists())->toBeFalse();
})->with([
    'one day early despite being old enough today' => ['2026-02-11', false],
    'exactly six weeks' => ['2026-02-12', true],
    'late historical dose' => ['2026-06-01', true],
]);

test('a missing or unscheduled dose number cannot bypass the age check', function (?int $doseNumber) {
    $version = VaccineScheduleVersion::create(['name' => 'Dose Test', 'version_code' => 'dose-test', 'status' => 'active']);
    VaccineSchedule::create([
        'vaccine_type_id' => $this->vaccine->id, 'vaccine_schedule_version_id' => $version->id,
        'dose_number' => 1, 'age_months' => 6, 'label' => 'Six months', 'active' => true,
    ]);
    try {
        app(VaccinationSubmissionService::class)->validate($this->nurse, $this->child, [
            'vaccine_type_id' => $this->vaccine->id, 'dose_number' => $doseNumber, 'administered_at' => '2026-01-01',
        ]);
        $this->fail('An unspecified schedule dose was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('dose_number');
    }
})->with([null, 10]);

test('age validation respects a series assigned to an older version', function () {
    $older = VaccineScheduleVersion::create(['name' => 'Older', 'version_code' => 'older-age', 'status' => 'archived']);
    $newer = VaccineScheduleVersion::create(['name' => 'Newer', 'version_code' => 'newer-age', 'status' => 'active']);
    foreach ([[$older, 2], [$newer, 6]] as [$version, $months]) {
        VaccineSchedule::create([
            'vaccine_type_id' => $this->vaccine->id, 'vaccine_schedule_version_id' => $version->id,
            'dose_number' => 1, 'age_months' => $months, 'label' => 'Test dose', 'active' => true,
        ]);
    }
    ChildVaccineSeriesVersion::create([
        'child_profile_id' => $this->child->id, 'vaccine_type_id' => $this->vaccine->id,
        'vaccine_schedule_version_id' => $older->id, 'assigned_at' => now(), 'assignment_reason' => 'test',
    ]);
    expect(app(VaccinationSubmissionService::class)->validate($this->nurse, $this->child, [
        'vaccine_type_id' => $this->vaccine->id, 'dose_number' => 1, 'administered_at' => '2026-03-01',
    ])['administered_at'])->toBe('2026-03-01');
});

test('parent edits and offline submissions enforce the same age rule', function () {
    Storage::fake('local');
    $parent = User::factory()->create(['role' => 'parent']);
    $version = VaccineScheduleVersion::create(['name' => 'Parent', 'version_code' => 'parent-age', 'status' => 'active']);
    VaccineSchedule::create([
        'vaccine_type_id' => $this->vaccine->id, 'vaccine_schedule_version_id' => $version->id,
        'dose_number' => 1, 'age_months' => 6, 'label' => 'Six months', 'active' => true,
    ]);
    $record = VaccinationRecord::create([
        'child_profile_id' => $this->child->id, 'vaccine_type_id' => $this->vaccine->id,
        'dose_number' => 1, 'administered_at' => '2026-07-01', 'recorded_by' => $parent->id,
        'submitted_by' => $parent->id, 'verification_status' => 'pending', 'source' => 'outside_clinic',
    ]);
    $input = [
        'vaccine_type_id' => $this->vaccine->id, 'dose_number' => 1, 'administered_at' => '2026-06-30',
        'clinic_name' => 'Clinic', 'proof_files' => [UploadedFile::fake()->image('proof.jpg')],
    ];
    try {
        app(VaccinationSubmissionService::class)->validate($parent, $this->child, $input, $record);
        $this->fail('An early parent edit was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('administered_at');
    }
    $record->delete();
    try {
        app(VaccinationSubmissionService::class)->syncOfflineBatch($this->nurse, $this->child, [$input]);
        $this->fail('An early offline dose was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('administered_at');
    }
    expect(VaccinationRecord::where('child_profile_id', $this->child->id)->count())->toBe(0);
});
