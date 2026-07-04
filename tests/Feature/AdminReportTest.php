<?php

use App\Models\AdverseEventReport;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineScheduleVersion;
use App\Models\VaccineType;
use Spatie\LaravelPdf\Facades\Pdf;

test('admins can view vaccination reports and export a pdf', function () {
    $startDate = now()->subDay()->toDateString();
    $endDate = now()->addDay()->toDateString();

    $barangay = Barangay::create(['name' => 'Barangay Uno', 'municipality' => 'Sample City']);
    $admin = User::factory()->create(['role' => 'admin']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Ana',
        'last_name' => 'Santos',
        'birthdate' => now()->subMonths(2)->toDateString(),
        'sex' => 'female',
        'guardian_name' => 'Maria Santos',
    ]);
    $vaccine = VaccineType::create(['code' => 'bcg-report', 'name' => 'BCG Report']);
    $scheduleVersion = VaccineScheduleVersion::create([
        'name' => 'PIDSP 2026 Revised July',
        'version_code' => '2026.1-report',
        'effective_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    VaccinationRecord::create([
        'child_profile_id' => $child->id,
        'vaccine_type_id' => $vaccine->id,
        'recorded_by' => $nurse->id,
        'dose_number' => 1,
        'source' => 'barangay_clinic',
        'verification_status' => 'verified',
        'suggested_schedule_version_id' => $scheduleVersion->id,
        'administered_at' => now()->toDateString(),
    ]);

    AdverseEventReport::create([
        'child_profile_id' => $child->id,
        'vaccine_type_id' => $vaccine->id,
        'reported_by' => $nurse->id,
        'event_date' => now()->toDateString(),
        'severity' => 'mild',
        'outcome' => 'Recovered',
        'symptoms' => 'Mild fever',
    ]);

    $this->actingAs($admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Vaccination report')
        ->assertSee('Barangay Uno')
        ->assertSee('BCG Report')
        ->assertSee('AEFI found')
        ->assertSee('Schedule version usage')
        ->assertSee('PIDSP 2026 Revised July');

    $this->actingAs($admin)
        ->get(route('reports.index', [
            'schedule_version' => $scheduleVersion->id,
            'include_aefi' => 1,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]))
        ->assertOk()
        ->assertSee('PIDSP 2026 Revised July')
        ->assertSee('2026.1-report')
        ->assertSee('Recent AEFI reports');

    Pdf::fake();

    $this->actingAs($admin)
        ->get(route('reports.pdf', [
            'schedule_version' => $scheduleVersion->id,
            'include_aefi' => 1,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]))
        ->assertOk();

    Pdf::assertRespondedWithPdf(fn ($pdf) => $pdf->contains(['Vaccination report', 'BCG Report', 'AEFI found', '2026.1-report', 'Recent AEFI reports']));
});

test('nurses cannot access admin reports', function () {
    $nurse = User::factory()->create(['role' => 'nurse']);

    $this->actingAs($nurse)
        ->get(route('reports.index'))
        ->assertForbidden();
});
