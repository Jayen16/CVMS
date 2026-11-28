<?php

use App\Console\Commands\GenerateSyncDemoData;
use App\Livewire\SyncDataPage;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\Municipality;
use App\Models\OfflineSyncOutbox;
use App\Models\Province;
use App\Models\Region;
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

test('central sync history follows location filters and keeps barangay options available', function () {
    config(['system.instance_type' => 'central']);
    $admin = User::factory()->create(['role' => 'superadmin', 'roles' => ['superadmin']]);
    $region = Region::create(['name' => 'Filter Region']);
    $province = Province::create(['name' => 'Filter Province', 'region_id' => $region->id]);
    $municipality = Municipality::create(['name' => 'Filter Municipality', 'province_id' => $province->id]);
    $barangays = collect(['First Barangay', 'Second Barangay'])->map(fn ($name) => Barangay::create(['name' => $name, 'municipality_id' => $municipality->id]));
    foreach ($barangays as $index => $barangay) {
        $child = Model::withoutEvents(fn () => ChildProfile::create([
            'barangay_id' => $barangay->id, 'created_by' => $admin->id,
            'first_name' => $index === 0 ? 'Selected' : 'Excluded', 'last_name' => 'SyncChild',
            'birthdate' => '2025-01-01', 'sex' => 'female', 'guardian_name' => 'Guardian',
            'sync_uuid' => (string) Str::uuid(), 'archived_at' => $index === 0 ? now() : null,
        ]));
        $uuid = (string) Str::uuid();
        DB::table('sync_processed_events')->insert([
            'id' => $uuid, 'event_uuid' => $uuid, 'facility_id' => GenerateSyncDemoData::FACILITY_UUID,
            'entity' => 'children', 'record_uuid' => $child->sync_uuid, 'operation' => 'created', 'version' => 1,
            'outcome' => 'applied', 'applied_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    SyncStatus::create(['scope' => 'global', 'last_attempted_at' => now()->subMinute(), 'last_synced_at' => now()]);
    $this->actingAs($admin);
    Livewire::test(SyncDataPage::class)->set('viewProcessedAll', true)
        ->assertSee('Selected SyncChild')->assertSee('Excluded SyncChild')
        ->set('regionId', $region->id)->set('provinceId', $province->id)->set('municipalityId', $municipality->id)
        ->set('barangayId', $barangays[0]->id)
        ->assertSee('Selected SyncChild')->assertDontSee('Excluded SyncChild')
        ->assertViewHas('barangays', fn ($options) => $options->count() === 2)
        ->set('barangayId', $barangays[1]->id)
        ->assertSee('Excluded SyncChild')->assertDontSee('Selected SyncChild')
        ->set('dateFilter', now()->toDateString())
        ->assertDontSee('Excluded SyncChild');
});
