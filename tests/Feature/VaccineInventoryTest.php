<?php

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineInventoryItem;
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

test('recording a clinic vaccination can consume and link one inventory dose', function () {
    $barangay = Barangay::create(['name' => 'Linked Inventory Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $vaccine = VaccineType::query()->firstOrFail();
    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Linked',
        'last_name' => 'Child',
        'birthdate' => now()->subYear()->toDateString(),
        'sex' => 'female',
        'guardian_name' => 'Guardian',
    ]);
    $item = VaccineInventoryItem::create([
        'item_code' => 'LINK-001',
        'barangay_id' => $barangay->id,
        'vaccine_type_id' => $vaccine->id,
        'batch_number' => 'LINK-BATCH',
        'received_at' => today(),
    ]);
    VaccineInventoryTransaction::create([
        'barangay_id' => $barangay->id,
        'vaccine_type_id' => $vaccine->id,
        'vaccine_inventory_item_id' => $item->id,
        'recorded_by' => $nurse->id,
        'transaction_type' => 'receipt',
        'movement' => 'in',
        'quantity' => 2,
        'transaction_date' => today(),
    ]);

    $this->actingAs($nurse)
        ->post(route('children.vaccinations.store', $child), [
            'vaccine_type_id' => $vaccine->id,
            'dose_number' => 1,
            'administered_at' => today()->toDateString(),
            'vaccine_inventory_item_id' => $item->id,
        ])
        ->assertRedirect(route('children.show', $child, absolute: false));

    $record = VaccinationRecord::query()->where('child_profile_id', $child->id)->firstOrFail();
    $usage = VaccineInventoryTransaction::query()->where('vaccination_record_id', $record->id)->firstOrFail();

    expect($usage->transaction_type)->toBe('usage')
        ->and($usage->quantity)->toBe(1)
        ->and($usage->vaccine_inventory_item_id)->toBe($item->id)
        ->and($item->fresh()->availableStock())->toBe(1);
});
