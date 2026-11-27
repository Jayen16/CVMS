<?php

use App\Jobs\BuildReportExport;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('exports require a selected region for super admins', function () {
    Queue::fake();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('reports.export.queue', 'both'), ['region_id' => 'all'])
        ->assertSessionHasErrors('region_id');

    expect(ReportExport::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('exports preserve filters and run without a separate queue worker', function () {
    Queue::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $region = Region::create(['name' => 'Export Region']);
    $filters = [
        'region_id' => $region->id,
        'province_id' => 'all',
        'municipality_id' => 'all',
        'barangay_id' => 'all',
        'schedule_version' => 'all',
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-27',
    ];

    $response = $this->actingAs($admin)->post(route('reports.export.queue', 'both'), $filters);
    $export = ReportExport::sole();

    $response->assertRedirect(route('reports.index', [...$filters, 'export' => $export->id]));
    Queue::assertPushed(BuildReportExport::class, fn ($job) => $job->connection === 'deferred');
});

test('report ZIP contains PDF and spreadsheet data only for accessible barangays', function () {
    Storage::fake('local');
    $region = Region::create(['name' => 'Export Region']);
    $province = Province::create(['name' => 'Export Province', 'region_id' => $region->id]);
    $municipality = Municipality::create(['name' => 'Export Town', 'province_id' => $province->id]);
    $otherMunicipality = Municipality::create(['name' => 'Other Town', 'province_id' => $province->id]);
    $barangay = Barangay::create(['name' => 'Export Barangay', 'municipality_id' => $municipality->id]);
    Barangay::create(['name' => 'Other Barangay', 'municipality_id' => $otherMunicipality->id]);
    $admin = User::factory()->create(['role' => 'municipal_admin', 'municipality_id' => $municipality->id]);
    $export = ReportExport::create([
        'user_id' => $admin->id,
        'format' => 'both',
        'filters' => ['start_date' => '2026-11-01', 'end_date' => '2026-11-27'],
    ]);

    (new BuildReportExport($export->id))->handle();

    $export->refresh();
    expect($export->status)->toBe('ready')->and($export->progress())->toBe(100)
        ->and((int) $export->total_items)->toBe(1);
    $zip = new ZipArchive;
    expect($zip->open($export->path))->toBeTrue()->and($zip->numFiles)->toBe(2);
    $prefix = 'Export Region/Export Province/Export Town/'.$barangay->name;
    expect($zip->getFromName($prefix.'/report.pdf'))->toStartWith('%PDF-')
        ->and($zip->getFromName($prefix.'/vaccination-records.csv'))->toContain('record_id');
    $zip->close();

    $this->actingAs($admin)->get(route('reports.export.status', $export))
        ->assertOk()->assertJson(['status' => 'ready', 'progress' => 100]);
    $this->get(route('reports.export.download', $export))->assertDownload();
    $otherAdmin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($otherAdmin)->get(route('reports.export.download', $export))->assertForbidden();
});

test('an export with no matching barangays fails instead of staying at zero percent', function () {
    $region = Region::create(['name' => 'Empty Export Region']);
    $admin = User::factory()->create(['role' => 'admin']);
    $export = ReportExport::create([
        'user_id' => $admin->id,
        'format' => 'both',
        'filters' => ['region_id' => $region->id],
    ]);

    expect(function () use ($export) {
        BuildReportExport::dispatch($export->id)->onConnection('sync');
    })
        ->toThrow(RuntimeException::class, 'No accessible barangays match');

    expect($export->fresh()->status)->toBe('failed');
});
