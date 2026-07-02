<?php

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;

test('nurses can view a child vaccination timeline chart', function () {
    $barangay = Barangay::create(['name' => 'Timeline Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);

    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Mika',
        'last_name' => 'Cruz',
        'birthdate' => now()->subMonths(10)->toDateString(),
        'sex' => 'female',
        'guardian_name' => 'Parent Cruz',
    ]);

    $this->actingAs($nurse)
        ->get(route('children.timeline', $child))
        ->assertOk()
        ->assertSee('vaccination timeline')
        ->assertSee('Routine schedule checklist')
        ->assertSee('Birth to 18 years scale')
        ->assertSee('13-18 yrs')
        ->assertSee('Action');
});

