<?php

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\PopulationBackground;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;

test('municipal admins can manage authorized population targets only in their municipality', function () {
    $region = Region::create(['name' => 'Population Region']);
    $province = Province::create(['name' => 'Population Province', 'region_id' => $region->id]);
    $municipality = Municipality::create(['name' => 'Population Municipality', 'province_id' => $province->id]);
    $barangay = Barangay::create(['name' => 'Population Barangay', 'municipality_id' => $municipality->id]);
    $admin = User::factory()->create(['role' => 'municipal_admin', 'roles' => ['municipal_admin'], 'municipality_id' => $municipality->id]);

    $this->actingAs($admin)->post(route('population-background.store'), [
        'municipality_id' => $municipality->id,
        'barangay_id' => $barangay->id,
        'reference_year' => 2026,
        'age_group' => '0–11 months',
        'sex' => 'both',
        'target_population' => 125,
        'source' => 'Municipal Health Office masterlist',
    ])->assertRedirect();

    expect(PopulationBackground::first()->target_population)->toBe(125);
    $this->actingAs($admin)->get(route('population-background.index'))->assertOk()->assertSee('Population Barangay');
});

test('barangay admins can view but cannot manage population targets', function () {
    $barangay = Barangay::create(['name' => 'Read Only Barangay']);
    $admin = User::factory()->create(['role' => 'barangay_admin', 'roles' => ['barangay_admin'], 'barangay_id' => $barangay->id]);

    $this->actingAs($admin)->post(route('population-background.store'), [
        'barangay_id' => $barangay->id,
        'reference_year' => 2026,
        'age_group' => '1–4 years',
        'sex' => 'both',
        'target_population' => 20,
        'source' => 'Official source',
    ])->assertForbidden();
});

test('nurses cannot access population background routes', function () {
    $nurse = User::factory()->create(['role' => 'nurse']);

    $this->actingAs($nurse)
        ->get(route('population-background.index'))
        ->assertForbidden();

    $this->actingAs($nurse)
        ->get(route('population-background.manage'))
        ->assertForbidden();

    $this->actingAs($nurse)
        ->get(route('population-background.template'))
        ->assertForbidden();
});
