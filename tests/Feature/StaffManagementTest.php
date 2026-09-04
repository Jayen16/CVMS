<?php

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;

test('municipal admins can create barangay admins in their municipality', function () {
    $region = Region::create(['name' => 'Staff Region']);
    $province = Province::create(['name' => 'Staff Province', 'region_id' => $region->id]);
    $municipality = Municipality::create(['name' => 'Staff Municipality', 'province_id' => $province->id]);
    $barangay = Barangay::create(['name' => 'Staff Barangay', 'municipality_id' => $municipality->id]);
    $municipalAdmin = User::factory()->create([
        'role' => 'municipal_admin',
        'roles' => ['municipal_admin'],
        'municipality_id' => $municipality->id,
    ]);

    $this->actingAs($municipalAdmin)
        ->get(route('municipal-admins.index'))
        ->assertOk()
        ->assertSee('Barangay admin accounts');

    $this->actingAs($municipalAdmin)
        ->post(route('nurses.store'), [
            'name' => 'New Barangay Admin',
            'email' => 'barangay-admin@example.com',
            'barangay_id' => $barangay->id,
        ])
        ->assertRedirect();

    $created = User::where('email', 'barangay-admin@example.com')->firstOrFail();
    expect($created->rolesList())->toBe(['barangay_admin'])
        ->and($created->municipality_id)->toBe($municipality->id)
        ->and($created->barangay_id)->toBe($barangay->id);
});

test('municipal admins cannot create nurses, but barangay admins can', function () {
    $region = Region::create(['name' => 'Nurse Region']);
    $province = Province::create(['name' => 'Nurse Province', 'region_id' => $region->id]);
    $municipality = Municipality::create(['name' => 'Nurse Municipality', 'province_id' => $province->id]);
    $barangay = Barangay::create(['name' => 'Nurse Barangay', 'municipality_id' => $municipality->id]);
    $otherBarangay = Barangay::create(['name' => 'Other Barangay', 'municipality_id' => $municipality->id]);
    $municipalAdmin = User::factory()->create([
        'role' => 'municipal_admin',
        'roles' => ['municipal_admin'],
        'municipality_id' => $municipality->id,
    ]);
    $barangayAdmin = User::factory()->create([
        'role' => 'barangay_admin',
        'roles' => ['barangay_admin'],
        'municipality_id' => $municipality->id,
        'barangay_id' => $barangay->id,
    ]);

    $this->actingAs($municipalAdmin)
        ->post(route('nurses.store'), [
            'name' => 'Not A Nurse',
            'email' => 'not-a-nurse@example.com',
            'barangay_id' => $otherBarangay->id,
        ])
        ->assertRedirect();

    expect(User::where('email', 'not-a-nurse@example.com')->value('role'))->toBe('barangay_admin');

    $this->actingAs($barangayAdmin)
        ->post(route('nurses.store'), [
            'name' => 'New Nurse',
            'email' => 'nurse@example.com',
            'phone' => '09171234567',
        ])
        ->assertRedirect();

    expect(User::where('email', 'nurse@example.com')->value('role'))->toBe('nurse');
});

test('barangay admins have operational authority within their barangay', function () {
    $admin = User::factory()->create([
        'role' => 'barangay_admin',
        'roles' => ['barangay_admin'],
        'barangay_id' => Barangay::create(['name' => 'Authority Barangay'])->id,
    ]);

    expect($admin->canManageChildren())->toBeTrue()
        ->and($admin->canVerifyVaccinations())->toBeTrue()
        ->and($admin->canSubmitAefiReports())->toBeTrue()
        ->and($admin->canManageInventory())->toBeTrue()
        ->and($admin->canMergeDuplicates())->toBeTrue();
});

test('barangay admins can customize a same-barangay nurse permissions and changes are audited', function () {
    $barangay = Barangay::create(['name' => 'Permissions Barangay']);
    $admin = User::factory()->create([
        'role' => 'barangay_admin',
        'roles' => ['barangay_admin'],
        'barangay_id' => $barangay->id,
    ]);
    $nurse = User::factory()->create([
        'role' => 'nurse',
        'roles' => ['nurse'],
        'barangay_id' => $barangay->id,
        'permissions' => User::defaultNursePermissions(),
    ]);

    $this->actingAs($admin)
        ->put(route('nurses.permissions.update', $nurse), [
            'permissions' => ['view_children', 'view_inventory'],
        ])
        ->assertRedirect(route('nurses.index'));

    $nurse->refresh();
    expect($nurse->nursePermissions())->toBe([
        'view_children',
        'view_inventory',
        'submit_aefi_reports',
        'view_aefi_reports',
        'view_duplicates',
        'merge_duplicates',
        'view_defaulters',
        'manage_announcements',
    ])
        ->and($nurse->canViewChildrenRegistry())->toBeTrue()
        ->and($nurse->canManageChildren())->toBeFalse()
        ->and($nurse->canViewInventory())->toBeTrue()
        ->and($nurse->canManageInventory())->toBeFalse()
        ->and(AuditLog::where('event', 'permissions_updated')->where('auditable_id', $nurse->id)->exists())->toBeTrue();
});

test('barangay admins cannot customize a nurse from another barangay', function () {
    $adminBarangay = Barangay::create(['name' => 'Admin Permissions Barangay']);
    $nurseBarangay = Barangay::create(['name' => 'Other Permissions Barangay']);
    $admin = User::factory()->create([
        'role' => 'barangay_admin',
        'roles' => ['barangay_admin'],
        'barangay_id' => $adminBarangay->id,
    ]);
    $nurse = User::factory()->create([
        'role' => 'nurse',
        'roles' => ['nurse'],
        'barangay_id' => $nurseBarangay->id,
    ]);

    $this->actingAs($admin)
        ->put(route('nurses.permissions.update', $nurse), ['permissions' => []])
        ->assertForbidden();
});
