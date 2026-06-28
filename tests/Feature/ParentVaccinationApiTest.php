<?php

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;

test('parents can submit outside clinic vaccination records for nurse verification', function () {
    $barangay = Barangay::create(['name' => 'Barangay Test']);
    $parent = User::factory()->create(['role' => 'parent']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $vaccine = VaccineType::create(['code' => 'test-vaccine', 'name' => 'Test Vaccine']);

    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'birthdate' => now()->subMonths(8)->toDateString(),
        'sex' => 'female',
        'guardian_name' => $parent->name,
    ]);

    $child->parents()->attach($parent->id, ['relationship' => 'mother']);

    $this->actingAs($parent)
        ->postJson(route('api.parent.children.vaccinations.store', $child), [
            'vaccine_type_id' => $vaccine->id,
            'dose_number' => 1,
            'administered_at' => now()->subDay()->toDateString(),
            'clinic_name' => 'Other Clinic',
            'clinic_location' => 'Nearby City',
        ])
        ->assertCreated()
        ->assertJsonPath('data.source', 'outside_clinic')
        ->assertJsonPath('data.verification_status', 'pending');

    $record = VaccinationRecord::firstOrFail();

    expect($record->submitted_by)->toBe($parent->id)
        ->and($record->verification_status)->toBe('pending');

    $this->actingAs($nurse)
        ->post(route('vaccinations.verify', $record))
        ->assertRedirect(route('children.show', $child, absolute: false));

    expect($record->fresh()->verification_status)->toBe('verified');
});

test('parents can submit vaccination history from the child profile page', function () {
    $barangay = Barangay::create(['name' => 'Web Parent Barangay']);
    $parent = User::factory()->create(['role' => 'parent']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $vaccine = VaccineType::create(['code' => 'web-vaccine', 'name' => 'Web Vaccine']);

    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Lia',
        'last_name' => 'Santos',
        'birthdate' => now()->subMonths(7)->toDateString(),
        'sex' => 'female',
        'guardian_name' => $parent->name,
    ]);

    $child->parents()->attach($parent->id, ['relationship' => 'mother']);

    $this->actingAs($parent)
        ->get(route('children.show', $child))
        ->assertOk()
        ->assertSee('Submit vaccination history')
        ->assertSee('Facility or clinic name');

    $this->actingAs($parent)
        ->post(route('children.vaccinations.store', $child), [
            'vaccine_type_id' => $vaccine->id,
            'dose_number' => 2,
            'administered_at' => now()->subDays(2)->toDateString(),
            'clinic_name' => 'Provincial Clinic',
            'clinic_location' => 'Nearby Municipality',
            'remarks' => 'Submitted from parent page.',
        ])
        ->assertRedirect(route('children.show', $child, absolute: false));

    $record = VaccinationRecord::firstOrFail();

    expect($record->source)->toBe('outside_clinic')
        ->and($record->verification_status)->toBe('pending')
        ->and($record->submitted_by)->toBe($parent->id)
        ->and($record->clinic_name)->toBe('Provincial Clinic')
        ->and($record->clinic_location)->toBe('Nearby Municipality');
});

test('parents cannot submit a duplicate dose number for the same vaccine and child', function () {
    $barangay = Barangay::create(['name' => 'Dose Guard Barangay']);
    $parent = User::factory()->create(['role' => 'parent']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $vaccine = VaccineType::create(['code' => 'dose-guard', 'name' => 'Dose Guard Vaccine']);

    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Ivy',
        'last_name' => 'Tan',
        'birthdate' => now()->subMonths(8)->toDateString(),
        'sex' => 'female',
        'guardian_name' => $parent->name,
    ]);

    $child->parents()->attach($parent->id, ['relationship' => 'mother']);

    VaccinationRecord::create([
        'child_profile_id' => $child->id,
        'vaccine_type_id' => $vaccine->id,
        'recorded_by' => $parent->id,
        'submitted_by' => $parent->id,
        'dose_number' => 1,
        'source' => 'outside_clinic',
        'verification_status' => 'pending',
        'administered_at' => now()->subDays(5)->toDateString(),
        'clinic_name' => 'Dose Clinic',
    ]);

    $this->actingAs($parent)
        ->post(route('children.vaccinations.store', $child), [
            'vaccine_type_id' => $vaccine->id,
            'dose_number' => 1,
            'administered_at' => now()->subDays(1)->toDateString(),
            'clinic_name' => 'Another Clinic',
            'clinic_location' => 'Nearby Municipality',
        ])
        ->assertSessionHasErrors(['dose_number']);
});

test('parents can edit their own pending vaccination history submissions', function () {
    $barangay = Barangay::create(['name' => 'Edit Barangay']);
    $parent = User::factory()->create(['role' => 'parent']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $vaccine = VaccineType::create(['code' => 'edit-vaccine', 'name' => 'Edit Vaccine']);

    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Cara',
        'last_name' => 'Uy',
        'birthdate' => now()->subMonths(6)->toDateString(),
        'sex' => 'female',
        'guardian_name' => $parent->name,
    ]);

    $child->parents()->attach($parent->id, ['relationship' => 'mother']);

    $record = VaccinationRecord::create([
        'child_profile_id' => $child->id,
        'vaccine_type_id' => $vaccine->id,
        'recorded_by' => $parent->id,
        'submitted_by' => $parent->id,
        'dose_number' => 1,
        'source' => 'outside_clinic',
        'verification_status' => 'pending',
        'administered_at' => now()->subDays(4)->toDateString(),
        'clinic_name' => 'Initial Clinic',
        'clinic_location' => 'Initial Town',
        'remarks' => 'Initial remarks',
    ]);

    $this->actingAs($parent)
        ->get(route('children.show', ['child' => $child, 'edit_record' => $record->id]))
        ->assertOk()
        ->assertSee('Edit pending vaccination history')
        ->assertSee('Save changes');

    $this->actingAs($parent)
        ->put(route('vaccinations.update', $record), [
            'vaccine_type_id' => $vaccine->id,
            'dose_number' => 2,
            'administered_at' => now()->subDays(2)->toDateString(),
            'clinic_name' => 'Updated Clinic',
            'clinic_location' => 'Updated Town',
            'remarks' => 'Updated remarks',
        ])
        ->assertRedirect(route('children.show', $child, absolute: false));

    expect($record->fresh()->dose_number)->toBe(2)
        ->and($record->fresh()->clinic_name)->toBe('Updated Clinic')
        ->and($record->fresh()->clinic_location)->toBe('Updated Town')
        ->and($record->fresh()->remarks)->toBe('Updated remarks')
        ->and($record->fresh()->verification_status)->toBe('pending');
});
