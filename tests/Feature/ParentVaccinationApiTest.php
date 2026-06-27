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
