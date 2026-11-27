<?php

use App\Livewire\ImmunizationSchedulePage;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Services\PredictiveAnalyticsService;
use Livewire\Livewire;

test('municipal planning includes children across batches and respects barangay scope', function () {
    $region = Region::create(['name' => 'Planning Region']);
    $province = Province::create(['name' => 'Planning Province', 'region_id' => $region->id]);
    $municipality = Municipality::create(['name' => 'Planning Municipality', 'province_id' => $province->id]);
    $barangays = collect(['First', 'Second'])->map(fn ($name) => Barangay::create([
        'name' => $name, 'municipality_id' => $municipality->id,
    ]));
    $admin = User::factory()->create(['role' => 'municipal_admin', 'municipality_id' => $municipality->id]);
    foreach (range(1, 26) as $index) {
        ChildProfile::create([
            'barangay_id' => $barangays[$index % 2]->id, 'created_by' => $admin->id,
            'first_name' => 'Planning', 'last_name' => 'Child '.$index,
            'birthdate' => today()->subDays(8), 'sex' => 'female', 'guardian_name' => 'Guardian',
        ]);
    }

    $analytics = app(PredictiveAnalyticsService::class);
    $risks = $analytics->missedDoseRisk($admin);
    expect($risks)->toHaveCount(26);
    foreach ($risks as $row) {
        expect($row['child']->relationLoaded('vaccinations'))->toBeFalse()
            ->and($row['child']->relationLoaded('seriesVersions'))->toBeFalse();
    }
    $demand = $analytics->vaccineDemand($admin);
    expect($demand->firstWhere('vaccine.name', 'BCG')['catch_up_backlog'])->toBe(26);

    $this->actingAs($admin)->get(route('predictive-analytics.index'))->assertOk();
    Livewire::actingAs($admin)->test(ImmunizationSchedulePage::class)
        ->assertViewHas('totalMatching', 26)
        ->assertViewHas('rows', fn ($rows) => $rows->count() === 10 && $rows->total() === 26)
        ->set('barangayId', $barangays[0]->id)
        ->assertViewHas('totalMatching', 13);
});
