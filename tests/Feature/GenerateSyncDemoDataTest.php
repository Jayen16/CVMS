<?php

use App\Livewire\SyncDataPage;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\OfflineSyncOutbox;
use App\Models\SyncStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('sync demo history is visible, repeatable and never queued for transmission', function () {
    config(['system.instance_type' => 'central']);
    Http::preventStrayRequests();
    $admin = User::factory()->create(['role' => 'superadmin', 'roles' => ['superadmin']]);
    $barangay = Barangay::create(['name' => 'Demo Sync Barangay']);
    Model::withoutEvents(fn () => ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $admin->id,
        'first_name' => 'Sample',
        'last_name' => 'Child',
        'birthdate' => '2025-01-01',
        'sex' => 'female',
        'guardian_name' => 'Demo Guardian',
        'address' => 'CVMS dummy population v1 | Demo Sync Barangay',
        'sync_uuid' => (string) Str::uuid(),
    ]));
    $queueCount = OfflineSyncOutbox::count();
    $realStatus = SyncStatus::create(['scope' => 'global', 'state' => 'idle']);

    $this->artisan('cvms:generate-sync-demo', ['--dry-run' => true])->assertSuccessful();
    expect(DB::table('sync_processed_events')->count())->toBe(0)
        ->and(SyncStatus::where('scope', 'demo')->exists())->toBeFalse();

    $this->artisan('cvms:generate-sync-demo')->assertSuccessful();
    $this->artisan('cvms:generate-sync-demo')->assertSuccessful();
    expect(DB::table('sync_processed_events')->count())->toBe(2)
        ->and(OfflineSyncOutbox::count())->toBe($queueCount)
        ->and($realStatus->fresh()->last_synced_at)->toBeNull();

    $this->actingAs($admin);
    Livewire::test(SyncDataPage::class)
        ->assertDontSee('Demonstration data')
        ->assertDontSee('[Demo]')
        ->assertSee('Sample Child');

    $realStatus->update([
        'last_attempted_at' => now()->subMinutes(5),
        'last_synced_at' => now()->subMinutes(4),
        'last_processed' => 7,
    ]);
    $this->artisan('cvms:generate-sync-demo')->assertSuccessful();
    expect($realStatus->fresh()->last_processed)->toBe(7);
    Livewire::test(SyncDataPage::class)
        ->assertDontSee('Demonstration data')
        ->assertDontSee('[Demo]')
        ->assertSee('Sample Child');
    Http::assertNothingSent();
});
