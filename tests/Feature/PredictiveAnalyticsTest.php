<?php

use App\Livewire\PredictiveAnalyticsPage;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\ChildVaccineSeriesVersion;
use App\Models\User;
use App\Models\VaccineSchedule;
use App\Models\VaccineScheduleVersion;
use App\Models\VaccineType;
use App\Services\PredictiveAnalyticsService;
use App\Support\PrivacyNotice;
use Livewire\Livewire;

test('authorized staff can view demand estimates and missed-dose risk outputs', function () {
    $barangay = Barangay::create(['name' => 'Analytics Barangay']);
    $admin = User::factory()->create(['role' => 'barangay_admin', 'barangay_id' => $barangay->id]);
    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $admin->id,
        'first_name' => 'Analytics',
        'last_name' => 'Child',
        'birthdate' => today()->subDays(8),
        'sex' => 'female',
        'guardian_name' => 'Guardian',
    ]);
    ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $admin->id,
        'first_name' => 'Demand',
        'last_name' => 'Child',
        'birthdate' => today(),
        'sex' => 'male',
        'guardian_name' => 'Guardian',
    ]);

    $analytics = app(PredictiveAnalyticsService::class);
    $demand = $analytics->vaccineDemand($admin);
    $risks = $analytics->missedDoseRisk($admin);

    expect($demand->pluck('vaccine.name'))->toContain('BCG')
        ->and($demand->firstWhere('vaccine.name', 'BCG')['estimated_demand'])->toBeGreaterThan(0)
        ->and($risks->firstWhere('child.id', $child->id)['suggestion']['status'])->toBe('overdue')
        ->and($risks->firstWhere('child.id', $child->id)['risk_level'])->toBe('medium');

    $this->actingAs($admin)
        ->get(route('predictive-analytics.index'))
        ->assertOk()
        ->assertSee('Estimated vaccine demand')
        ->assertSee('Available stock');

    $this->actingAs($admin)
        ->get(route('schedule-monitoring.index'))
        ->assertOk()
        ->assertSee('Schedule monitoring')
        ->assertSee('Analytics Child')
        ->assertSee('Missed-dose risk');
});

test('parents cannot access predictive analytics', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'privacy_notice_version' => PrivacyNotice::VERSION,
    ]);

    $this->actingAs($parent)
        ->get(route('predictive-analytics.index'))
        ->assertForbidden();
});

test('forecast filters recalculate balances while retaining the same overdue backlog', function () {
    $this->travelTo(now()->setDate(2026, 11, 15)->startOfDay());
    $barangay = Barangay::create(['name' => 'Forecast Filter Barangay']);
    $admin = User::factory()->create(['role' => 'barangay_admin', 'barangay_id' => $barangay->id]);
    $child = ChildProfile::create([
        'barangay_id' => $barangay->id, 'created_by' => $admin->id,
        'first_name' => 'Forecast', 'last_name' => 'Child', 'birthdate' => today()->subDay(),
        'sex' => 'female', 'guardian_name' => 'Guardian',
    ]);
    $vaccine = VaccineType::create(['code' => 'FORECAST-TEST', 'name' => 'Forecast test vaccine', 'active' => true]);
    $versions = collect([2, 4])->map(function (int $secondDoseMonth) use ($vaccine) {
        $version = VaccineScheduleVersion::create([
            'name' => 'Forecast schedule '.$secondDoseMonth, 'version_code' => 'FORECAST-'.$secondDoseMonth,
            'effective_date' => '2026-01-01', 'status' => 'archived',
        ]);
        foreach ([0, $secondDoseMonth, 8] as $index => $ageMonths) {
            VaccineSchedule::create([
                'vaccine_type_id' => $vaccine->id, 'vaccine_schedule_version_id' => $version->id,
                'dose_number' => $index + 1, 'age_months' => $ageMonths, 'label' => 'Test dose', 'active' => true,
            ]);
        }

        return $version;
    });

    $component = Livewire::actingAs($admin)->test(PredictiveAnalyticsPage::class)
        ->set('scheduleVersion', $versions[0]->id);

    // The overdue birth dose is counted once in every period; later doses enter as the horizon grows.
    foreach ([1 => 1, 3 => 2, 6 => 2, 12 => 3] as $months => $expectedDemand) {
        $component->set('months', $months)
            ->assertViewHas('forecastMonths', $months)
            ->assertViewHas('demand', function ($demand) use ($vaccine, $expectedDemand) {
                $row = $demand->firstWhere('vaccine.id', $vaccine->id);

                return $row['catch_up_backlog'] === 1
                    && $row['estimated_demand'] === $expectedDemand
                    && $row['projected_balance'] === -$expectedDemand;
            });
    }

    $component->set('months', 3)->set('scheduleVersion', $versions[1]->id)
        ->assertViewHas('demand', fn ($demand) => $demand->firstWhere('vaccine.id', $vaccine->id)['projected_balance'] === -1);

    // An assigned series keeps its original schedule when the planning fallback changes.
    ChildVaccineSeriesVersion::create([
        'child_profile_id' => $child->id, 'vaccine_type_id' => $vaccine->id,
        'vaccine_schedule_version_id' => $versions[0]->id,
        'assigned_at' => now(), 'assignment_reason' => 'resolved_from_existing_series',
    ]);
    $component->set('scheduleVersion', $versions[0]->id)->set('scheduleVersion', $versions[1]->id)
        ->assertViewHas('demand', fn ($demand) => $demand->firstWhere('vaccine.id', $vaccine->id)['projected_balance'] === -2);
});
