<?php

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use Spatie\LaravelPdf\Facades\Pdf;

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

test('nurses can export a child vaccination timeline pdf', function () {
    $barangay = Barangay::create(['name' => 'Timeline PDF Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);

    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Lia',
        'last_name' => 'Reyes',
        'birthdate' => now()->subMonths(9)->toDateString(),
        'sex' => 'female',
        'guardian_name' => 'Parent Reyes',
    ]);

    Pdf::fake();

    $this->actingAs($nurse)
        ->get(route('children.timeline.pdf', $child))
        ->assertOk();

    Pdf::assertRespondedWithPdf(fn ($pdf) => $pdf->contains([$child->full_name, 'vaccination timeline']));
});

