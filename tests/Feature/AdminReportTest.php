<?php

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;
use Spatie\LaravelPdf\Facades\Pdf;

test('admins can view vaccination reports and export a pdf', function () {
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

    VaccinationRecord::create([
        'child_profile_id' => $child->id,
        'vaccine_type_id' => $vaccine->id,
        'recorded_by' => $nurse->id,
        'dose_number' => 1,
        'source' => 'barangay_clinic',
        'verification_status' => 'verified',
        'administered_at' => now()->toDateString(),
    ]);

    $this->actingAs($admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Vaccination report')
        ->assertSee('Barangay Uno')
        ->assertSee('BCG Report');

    Pdf::fake();

    $this->actingAs($admin)
        ->get(route('reports.pdf'))
        ->assertOk();

    Pdf::assertRespondedWithPdf(fn ($pdf) => $pdf->contains(['Vaccination report', 'BCG Report']));
});

test('nurses cannot access admin reports', function () {
    $nurse = User::factory()->create(['role' => 'nurse']);

    $this->actingAs($nurse)
        ->get(route('reports.index'))
        ->assertForbidden();
});
