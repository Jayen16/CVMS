<?php

use App\Livewire\DuplicateChildrenPage;
use App\Models\AdverseEventReport;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\ChildVaccineSeriesVersion;
use App\Models\ClinicAnnouncement;
use App\Models\OfflineSyncOutbox;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccinationReminder;
use App\Models\VaccineScheduleVersion;
use App\Models\VaccineType;
use App\Services\DuplicateChildDetectionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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

test('parent dashboard shows the due calendar without announcements', function () {
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
        ->assertDontSee('Saturday vaccine day')
        ->assertDontSee('Clinic announcements');
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

test('duplicate merge keeps the selected child and moves linked records', function () {
    $barangay = Barangay::create(['name' => 'Merge Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $parent = User::factory()->create(['role' => 'parent']);
    $vaccine = VaccineType::query()->firstOrFail();
    $scheduleVersion = VaccineScheduleVersion::query()->firstOrFail();

    $target = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Ivy',
        'last_name' => 'Dela Cruz',
        'birthdate' => now()->subMonths(9)->toDateString(),
        'sex' => 'female',
        'guardian_name' => 'Parent One',
        'guardian_contact' => '09170000010',
    ]);

    $duplicate = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Ivy',
        'last_name' => 'Dela Cruz',
        'birthdate' => $target->birthdate->toDateString(),
        'sex' => 'female',
        'guardian_name' => 'Parent Two',
        'guardian_contact' => '09170000010',
    ]);

    $duplicate->parents()->attach($parent->id, ['relationship' => 'mother']);

    $record = VaccinationRecord::create([
        'child_profile_id' => $duplicate->id,
        'vaccine_type_id' => $vaccine->id,
        'recorded_by' => $nurse->id,
        'dose_number' => 1,
        'source' => 'barangay_clinic',
        'verification_status' => 'verified',
        'verified_by' => $nurse->id,
        'verified_at' => now(),
        'administered_at' => now()->subDay()->toDateString(),
    ]);

    $report = AdverseEventReport::create([
        'child_profile_id' => $duplicate->id,
        'vaccination_record_id' => $record->id,
        'vaccine_type_id' => $vaccine->id,
        'reported_by' => $nurse->id,
        'event_date' => now()->toDateString(),
        'severity' => 'mild',
        'outcome' => 'Recovered',
        'symptoms' => 'Fever',
    ]);

    VaccinationReminder::create([
        'child_profile_id' => $duplicate->id,
        'parent_id' => $parent->id,
        'vaccine_name' => 'BCG',
        'dose_number' => 1,
        'due_at' => now()->addWeek()->toDateString(),
        'channel' => 'email',
        'recipient' => $parent->email,
        'status' => 'pending',
    ]);

    ChildVaccineSeriesVersion::create([
        'child_profile_id' => $duplicate->id,
        'vaccine_type_id' => $vaccine->id,
        'vaccine_schedule_version_id' => $scheduleVersion->id,
        'assigned_at' => now(),
        'assignment_reason' => 'Initial assignment',
    ]);

    $signature = app(DuplicateChildDetectionService::class)
        ->detect(ChildProfile::query()->whereKey([$target->id, $duplicate->id])->get())[0]['signature'];

    $this->actingAs($nurse);

    Livewire::test(DuplicateChildrenPage::class)
        ->call('mergeGroup', $signature, $target->id)
        ->assertHasNoErrors();

    expect(ChildProfile::find($duplicate->id))->toBeNull();
    expect($record->fresh()->child_profile_id)->toBe($target->id);
    expect($report->fresh()->child_profile_id)->toBe($target->id);
    expect(VaccinationReminder::where('child_profile_id', $target->id)->count())->toBe(1);
    expect(ChildVaccineSeriesVersion::where('child_profile_id', $target->id)->count())->toBe(1);
    expect($target->parents()->whereKey($parent->id)->exists())->toBeTrue();
});

test('superadmin can review duplicate matches across barangays', function () {
    $north = Barangay::create(['name' => 'North Barangay']);
    $south = Barangay::create(['name' => 'South Barangay']);
    $superadmin = User::factory()->create(['role' => 'admin']);
    $northNurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $north->id]);
    $southNurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $south->id]);

    ChildProfile::create([
        'barangay_id' => $north->id,
        'created_by' => $northNurse->id,
        'first_name' => 'Mila',
        'last_name' => 'Reyes',
        'birthdate' => now()->subMonths(8)->toDateString(),
        'sex' => 'female',
        'guardian_name' => 'Guardian A',
        'guardian_contact' => '09170000020',
    ]);

    ChildProfile::create([
        'barangay_id' => $south->id,
        'created_by' => $southNurse->id,
        'first_name' => 'Mila',
        'last_name' => 'Reyes',
        'birthdate' => now()->subMonths(8)->toDateString(),
        'sex' => 'female',
        'guardian_name' => 'Guardian B',
        'guardian_contact' => '09170000021',
    ]);

    $this->actingAs($superadmin)
        ->get(route('duplicates.index'))
        ->assertOk()
        ->assertSee('Potential duplicate child records')
        ->assertSee('North Barangay')
        ->assertSee('South Barangay');
});

test('duplicate detection collapses identical child clusters into one group', function () {
    $barangay = Barangay::create(['name' => 'Deduped Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);

    foreach (range(1, 4) as $index) {
        ChildProfile::create([
            'barangay_id' => $barangay->id,
            'created_by' => $nurse->id,
            'first_name' => 'Lio',
            'last_name' => 'Torres',
            'birthdate' => now()->subMonths(8)->toDateString(),
            'sex' => 'male',
            'guardian_name' => 'Guardian '.$index,
            'guardian_contact' => '09170000999',
        ]);
    }

    $children = ChildProfile::query()->where('barangay_id', $barangay->id)->get();

    $groups = app(DuplicateChildDetectionService::class)->detect($children);

    expect($groups)->toHaveCount(1);
    expect($groups[0]['children'])->toHaveCount(4);
    expect($groups[0]['reason'])->toContain('Same birthdate and child name');
    expect($groups[0]['reason'])->toContain('Same birthdate and guardian contact');
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
