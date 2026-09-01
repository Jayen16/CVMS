<?php

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\OfflineSyncOutbox;
use App\Models\SyncStatus;
use App\Models\User;
use App\Services\OfflineSyncService;

test('barangay admin can transfer a child to another barangay', function () {
    $origin = Barangay::create(['name' => 'Origin Barangay']);
    $destination = Barangay::create(['name' => 'Destination Barangay']);
    $creator = User::factory()->create(['role' => 'nurse', 'barangay_id' => $origin->id]);
    $admin = User::factory()->create(['role' => 'barangay_admin', 'barangay_id' => $origin->id]);

    $child = ChildProfile::create([
        'barangay_id' => $origin->id,
        'created_by' => $creator->id,
        'first_name' => 'Milo',
        'last_name' => 'Tan',
        'birthdate' => now()->subMonths(7)->toDateString(),
        'sex' => 'male',
        'guardian_name' => 'Parent Tan',
    ]);

    $this->actingAs($admin)
        ->post(route('children.transfer', $child), [
            'barangay_id' => $destination->id,
        ])
        ->assertRedirect(route('children.index', absolute: false));

    expect($child->fresh()->barangay_id)->toBe($destination->id);
});

test('manual sync records the latest sync timestamp', function () {
    $barangay = Barangay::create(['name' => 'Sync Barangay']);
    $admin = User::factory()->create(['role' => 'barangay_admin', 'barangay_id' => $barangay->id]);
    $outbox = OfflineSyncOutbox::create([
        'model_type' => ChildProfile::class,
        'model_sync_uuid' => '11111111-1111-1111-1111-111111111111',
        'operation' => 'upsert',
        'payload' => ['sync_uuid' => '11111111-1111-1111-1111-111111111111'],
        'queued_at' => now(),
    ]);

    $this->mock(OfflineSyncService::class, function ($mock) use ($outbox): void {
        $mock->shouldReceive('syncPending')->once()->andReturn(['processed' => 1, 'failed' => 0]);
    });

    $this->actingAs($admin)
        ->get(route('sync.index'))
        ->assertOk()
        ->assertSee('Sync data');

    $this->actingAs($admin)
        ->post(route('sync.manual'))
        ->assertRedirect(route('sync.index', absolute: false));

    $status = SyncStatus::where('scope', 'global')->first();

    expect($status)->not->toBeNull();
    expect($status->last_processed)->toBe(1);
    expect($status->last_failed)->toBe(0);
    expect($status->last_synced_by)->toBe($admin->id);

    $outbox->refresh();
});

test('parents cannot access the sync data page', function () {
    $parent = User::factory()->create(['role' => 'parent']);

    $this->actingAs($parent)
        ->get(route('sync.index'))
        ->assertForbidden();
});

test('nurses cannot access or run manual sync', function () {
    $barangay = Barangay::create(['name' => 'Nurse Sync Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);

    $this->actingAs($nurse)
        ->get(route('sync.index'))
        ->assertForbidden();

    $this->actingAs($nurse)
        ->post(route('sync.manual'))
        ->assertForbidden();
});
