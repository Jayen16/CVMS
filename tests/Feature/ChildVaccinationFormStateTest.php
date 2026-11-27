<?php

use App\Livewire\ChildShowPage;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccineSchedule;
use App\Models\VaccineScheduleVersion;
use App\Models\VaccineType;
use Livewire\Livewire;

test('an early vaccination keeps the history tab and recording form open after redirect', function () {
    $this->travelTo(now()->setDate(2026, 11, 28)->startOfDay());
    $barangay = Barangay::create(['name' => 'Form State Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $child = ChildProfile::create([
        'barangay_id' => $barangay->id, 'created_by' => $nurse->id,
        'first_name' => 'Form', 'last_name' => 'State',
        'birthdate' => '2026-09-12', 'sex' => 'female', 'guardian_name' => 'Guardian',
    ]);
    $vaccine = VaccineType::create(['name' => 'Form State Vaccine', 'code' => 'FORM-STATE', 'active' => true]);
    VaccineScheduleVersion::query()->update(['status' => 'draft']);
    $version = VaccineScheduleVersion::create(['name' => 'Form State', 'version_code' => 'form-state', 'status' => 'active']);
    VaccineSchedule::create([
        'vaccine_type_id' => $vaccine->id, 'vaccine_schedule_version_id' => $version->id,
        'dose_number' => 1, 'age_months' => 12, 'label' => 'Twelve months', 'active' => true,
    ]);

    $this->actingAs($nurse)->from(route('children.show', $child))
        ->post(route('children.vaccinations.store', $child), [
            'vaccine_type_id' => $vaccine->id, 'dose_number' => 1, 'administered_at' => '2026-11-28',
        ])
        ->assertRedirect(route('children.show', $child))
        ->assertSessionHasErrors('administered_at')
        ->assertSessionHasInput('vaccine_type_id', $vaccine->id);

    $this->get(route('children.show', $child))
        ->assertOk()
        ->assertSee('This dose cannot be recorded before');

    // The redirected page shares its session errors with the Livewire component.
    Livewire::test(ChildShowPage::class, ['child' => $child])
        ->assertSet('childTab', 'vaccination')
        ->assertSet('recordVaccinationOpen', true)
        ->set('perPage', 25)
        ->assertSet('childTab', 'vaccination')
        ->assertSet('recordVaccinationOpen', true);
});
