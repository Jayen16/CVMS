<?php

use App\Models\User;
use App\Models\VaccineSchedule;
use App\Models\VaccineType;

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
