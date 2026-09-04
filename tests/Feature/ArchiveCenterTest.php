<?php

use App\Models\AdverseEventReport;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccineType;

test('archive with no matching records returns to the archive center with an error', function () {
    $admin = User::factory()->create(['role' => 'superadmin', 'roles' => ['superadmin']]);

    $this->actingAs($admin)
        ->post(route('archives.store'), [
            'type' => 'aefi',
            'date_from' => '2025-01-01',
            'date_to' => '2025-12-31',
            'archive_reason' => 'Closed reporting year',
        ])
        ->assertRedirect(route('archives.index'))
        ->assertSessionHasErrors([
            'archive' => 'No active records matched that type and date range.',
        ]);
});

test('authorized staff can archive matching AEFI reports by date range', function () {
    $barangay = Barangay::create(['name' => 'Report Archive Barangay']);
    $admin = User::factory()->create([
        'role' => 'barangay_admin',
        'roles' => ['barangay_admin'],
        'barangay_id' => $barangay->id,
    ]);
    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $admin->id,
        'first_name' => 'Report',
        'last_name' => 'Child',
        'birthdate' => '2021-01-01',
        'sex' => 'male',
        'guardian_name' => 'Guardian',
    ]);
    $vaccine = VaccineType::create(['name' => 'Archive Vaccine', 'code' => 'ARCH', 'active' => true]);
    $report = AdverseEventReport::create([
        'child_profile_id' => $child->id,
        'vaccine_type_id' => $vaccine->id,
        'reported_by' => $admin->id,
        'event_date' => '2025-06-15',
        'severity' => 'mild',
        'symptoms' => 'Fever',
    ]);

    $this->actingAs($admin)
        ->post(route('archives.store'), [
            'type' => 'aefi',
            'date_from' => '2025-01-01',
            'date_to' => '2025-12-31',
            'archive_reason' => '2025 reporting cycle complete',
        ])
        ->assertRedirect(route('archives.index'));

    expect(AdverseEventReport::find($report->id))->toBeNull()
        ->and(AdverseEventReport::withoutGlobalScope('not_archived')->find($report->id)->isArchived())->toBeTrue()
        ->and(AuditLog::where('event', 'reports_archived')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('archives.restore', ['type' => 'aefi', 'recordId' => $report->id]))
        ->assertRedirect(route('archives.index'));

    expect(AdverseEventReport::find($report->id)->isArchived())->toBeFalse();
});

test('nurses cannot access the report archive center', function () {
    $barangay = Barangay::create(['name' => 'Nurse Report Archive Barangay']);
    $nurse = User::factory()->create([
        'role' => 'nurse',
        'roles' => ['nurse'],
        'barangay_id' => $barangay->id,
        'permissions' => ['view_children'],
    ]);

    $this->actingAs($nurse)
        ->get(route('archives.index'))
        ->assertForbidden();

    $this->actingAs($nurse)
        ->get(route('archives.index'))
        ->assertForbidden();
});
