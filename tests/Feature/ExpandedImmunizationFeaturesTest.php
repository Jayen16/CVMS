<?php

use App\Models\AdverseEventReport;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\ClinicAnnouncement;
use App\Models\OfflineSyncOutbox;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('parent submissions can include photo proof and appear on the digital vaccine card', function () {
    Storage::fake('public');

    $barangay = Barangay::create(['name' => 'Proof Barangay']);
    $parent = User::factory()->create(['role' => 'parent']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $vaccine = VaccineType::query()->firstOrFail();

    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Mika',
        'last_name' => 'Lopez',
        'birthdate' => now()->subMonths(6)->toDateString(),
        'sex' => 'female',
        'guardian_name' => $parent->name,
    ]);

    $child->parents()->attach($parent->id, ['relationship' => 'mother']);

    $this->actingAs($parent)
        ->post(route('children.vaccinations.store', $child), [
            'vaccine_type_id' => $vaccine->id,
            'dose_number' => 1,
            'administered_at' => now()->subDay()->toDateString(),
            'clinic_name' => 'Outside Clinic',
            'clinic_location' => 'City',
            'proof_file' => UploadedFile::fake()->image('proof.jpg'),
        ])
        ->assertRedirect(route('children.show', $child, absolute: false));

    $record = VaccinationRecord::firstOrFail();

    expect($record->proof_path)->not->toBeNull();
    Storage::disk('public')->assertExists($record->proof_path);
    expect(OfflineSyncOutbox::where('model_type', VaccinationRecord::class)->count())->toBeGreaterThan(0);

    $this->actingAs($parent)
        ->get(route('children.card', $child))
        ->assertOk()
        ->assertSee('Digital child vaccine card')
        ->assertSee($child->full_name);

    $this->get(route('vaccine-cards.validate', $child->vaccine_card_token))
        ->assertOk()
        ->assertSee($child->full_name);
});

test('parent dashboard shows due calendar and clinic announcements', function () {
    $barangay = Barangay::create(['name' => 'Calendar Barangay']);
    $parent = User::factory()->create(['role' => 'parent']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);

    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Noah',
        'last_name' => 'Rivera',
        'birthdate' => now()->subMonths(6)->toDateString(),
        'sex' => 'male',
        'guardian_name' => $parent->name,
    ]);

    $child->parents()->attach($parent->id, ['relationship' => 'father']);

    ClinicAnnouncement::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'title' => 'Saturday vaccine day',
        'category' => 'schedule',
        'audience' => 'parents',
        'starts_on' => today(),
        'message' => 'Bring your child vaccine card.',
        'active' => true,
    ]);

    $this->actingAs($parent)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('This month’s family due calendar')
        ->assertSee('Saturday vaccine day');
});

test('staff can use verification queue, defaulter list, and duplicate detection', function () {
    $barangay = Barangay::create(['name' => 'Review Barangay']);
    $parent = User::factory()->create(['role' => 'parent']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $vaccine = VaccineType::query()->firstOrFail();

    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Lena',
        'last_name' => 'Cruz',
        'birthdate' => now()->subMonths(7)->toDateString(),
        'sex' => 'female',
        'guardian_name' => $parent->name,
        'guardian_contact' => '09170000001',
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
        'administered_at' => now()->subDays(2)->toDateString(),
        'clinic_name' => 'Review Clinic',
    ]);

    ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Lena',
        'last_name' => 'Cruz',
        'birthdate' => $child->birthdate->toDateString(),
        'sex' => 'female',
        'guardian_name' => 'Other Guardian',
        'guardian_contact' => '09170000001',
    ]);

    $this->actingAs($nurse)
        ->get(route('verification-queue.index', ['source' => 'outside_clinic']))
        ->assertOk()
        ->assertSee('Pending verification queue')
        ->assertSee($child->full_name);

    $this->actingAs($nurse)
        ->get(route('defaulters.index', ['days' => 7]))
        ->assertOk()
        ->assertSee('Defaulter and recall list')
        ->assertSee($child->full_name);

    $this->actingAs($nurse)
        ->get(route('duplicates.index'))
        ->assertOk()
        ->assertSee('Potential duplicate child records')
        ->assertSee($child->full_name);
});

test('staff can save aefi reports and review them later', function () {
    $barangay = Barangay::create(['name' => 'AEFI Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $vaccine = VaccineType::query()->firstOrFail();

    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Ava',
        'last_name' => 'Garcia',
        'birthdate' => now()->subMonths(5)->toDateString(),
        'sex' => 'female',
        'guardian_name' => 'Parent Garcia',
    ]);

    $record = VaccinationRecord::create([
        'child_profile_id' => $child->id,
        'vaccine_type_id' => $vaccine->id,
        'recorded_by' => $nurse->id,
        'dose_number' => 1,
        'source' => 'barangay_clinic',
        'verification_status' => 'verified',
        'verified_by' => $nurse->id,
        'verified_at' => now(),
        'administered_at' => now()->subDay()->toDateString(),
    ]);

    $this->actingAs($nurse)
        ->post(route('children.aefi-reports.store', $child), [
            'vaccination_record_id' => $record->id,
            'vaccine_type_id' => $vaccine->id,
            'event_date' => now()->toDateString(),
            'severity' => 'mild',
            'outcome' => 'Recovered',
            'symptoms' => 'Fever and swelling',
        ])
        ->assertRedirect(route('children.show', $child, absolute: false));

    expect(AdverseEventReport::count())->toBe(1);

    $this->actingAs($nurse)
        ->get(route('aefi-reports.index'))
        ->assertOk()
        ->assertSee('Fever and swelling');
});

test('offline sync stores nurse vaccination records idempotently', function () {
    $barangay = Barangay::create(['name' => 'Offline Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $vaccine = VaccineType::query()->firstOrFail();

    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Kai',
        'last_name' => 'Santos',
        'birthdate' => now()->subMonths(4)->toDateString(),
        'sex' => 'male',
        'guardian_name' => 'Parent Santos',
    ]);

    $payload = [
        'records' => [[
            'vaccine_type_id' => $vaccine->id,
            'dose_number' => 1,
            'administered_at' => now()->subDay()->toDateString(),
            'remarks' => 'Queued offline',
            'client_submission_id' => 'offline-sync-1',
        ]],
    ];

    $this->actingAs($nurse)
        ->postJson(route('api.parent.children.offline-sync', $child), $payload)
        ->assertOk()
        ->assertJsonPath('message', 'Offline vaccination records synced.');

    $this->actingAs($nurse)
        ->postJson(route('api.parent.children.offline-sync', $child), $payload)
        ->assertOk();

    expect(VaccinationRecord::where('child_profile_id', $child->id)->count())->toBe(1);
    expect(OfflineSyncOutbox::where('model_type', VaccinationRecord::class)->count())->toBeGreaterThan(0);
});
