<?php

use App\Models\Barangay;
use App\Models\User;
use App\Models\VaccineInventoryTransaction;
use App\Models\VaccineType;

test('authorized staff can record inventory receipts and view the balance', function () {
    $barangay = Barangay::create(['name' => 'Inventory Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $vaccine = VaccineType::query()->firstOrFail();

    $this->actingAs($nurse)
        ->post(route('vaccine-inventory.store'), [
            'barangay_id' => $barangay->id,
            'vaccine_type_id' => $vaccine->id,
            'transaction_type' => 'receipt',
            'movement' => 'in',
            'quantity' => 50,
            'batch_number' => 'BATCH-001',
            'transaction_date' => today()->toDateString(),
        ])
        ->assertRedirect(route('vaccine-inventory.index', absolute: false));

    expect(VaccineInventoryTransaction::first()->quantity)->toBe(50);

    $this->actingAs($nurse)
        ->get(route('vaccine-inventory.index'))
        ->assertOk()
        ->assertSee($vaccine->name)
        ->assertSee('50');
});

test('inventory is scoped to the staff barangay and parents are denied', function () {
    $firstBarangay = Barangay::create(['name' => 'First Inventory Barangay']);
    $secondBarangay = Barangay::create(['name' => 'Second Inventory Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $firstBarangay->id]);
    $parent = User::factory()->create(['role' => 'parent']);
    $vaccine = VaccineType::query()->firstOrFail();

    VaccineInventoryTransaction::create([
        'barangay_id' => $secondBarangay->id,
        'vaccine_type_id' => $vaccine->id,
        'recorded_by' => $nurse->id,
        'transaction_type' => 'receipt',
        'movement' => 'in',
        'quantity' => 80,
        'transaction_date' => today(),
    ]);

    $this->actingAs($nurse)->get(route('vaccine-inventory.index'))->assertOk()->assertDontSee('Second Inventory Barangay');
    $this->actingAs($parent)->get(route('vaccine-inventory.index'))->assertForbidden();
});

test('inventory cannot remove more stock than is available', function () {
    $barangay = Barangay::create(['name' => 'Stock Guard Barangay']);
    $admin = User::factory()->create(['role' => 'barangay_admin', 'barangay_id' => $barangay->id]);
    $vaccine = VaccineType::query()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('vaccine-inventory.store'), [
            'barangay_id' => $barangay->id,
            'vaccine_type_id' => $vaccine->id,
            'transaction_type' => 'usage',
            'movement' => 'out',
            'quantity' => 1,
            'transaction_date' => today()->toDateString(),
        ])
        ->assertSessionHasErrors('quantity');

    expect(VaccineInventoryTransaction::count())->toBe(0);
});
