<?php

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use App\Services\PredictiveAnalyticsService;

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
    $parent = User::factory()->create(['role' => 'parent']);

    $this->actingAs($parent)
        ->get(route('predictive-analytics.index'))
        ->assertForbidden();
});
