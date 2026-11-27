<?php

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\Municipality;
use App\Models\PopulationBackground;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;
use Illuminate\Support\Facades\Hash;

test('dummy administration seeds requested accounts and archives without duplicating or overwriting records', function (bool $dryRun) {
    $region = Region::create(['name' => 'Test Region']);
    $province = Province::create(['name' => 'Cavite', 'region_id' => $region->id]);
    $municipality = Municipality::create(['name' => 'Indang', 'province_id' => $province->id]);
    foreach (['Kayquit III', 'Lumampong Balagbag', 'Barangay 4'] as $name) {
        Barangay::create(['name' => $name, 'municipality_id' => $municipality->id]);
    }
    $outside = Barangay::create(['name' => 'Outside']);
    $existing = User::factory()->create(['barangay_id' => $outside->id]);
    VaccineType::create(['name' => 'BCG', 'code' => 'BCG', 'active' => true]);
    $barangay = Barangay::where('name', 'Kayquit III')->firstOrFail();
    $official = PopulationBackground::create([
        'barangay_id' => $barangay->id, 'municipality_id' => $municipality->id,
        'reference_year' => now()->year, 'age_group' => '0–11 months', 'sex' => 'female',
        'target_population' => 999, 'source' => 'Existing official target', 'created_by' => $existing->id,
    ]);
    $before = $existing->fresh()->toArray();

    $this->artisan('cvms:seed-dummy-administration', ['--dry-run' => $dryRun])->assertSuccessful();

    expect($existing->fresh()->toArray())->toBe($before);
    expect($official->fresh()->target_population)->toBe(999);
    if ($dryRun) {
        expect(User::count())->toBe(1);
        expect(PopulationBackground::count())->toBe(1);
        expect(ChildProfile::count())->toBe(0);
        expect(VaccinationRecord::archived()->count())->toBe(0);

        return;
    }

    foreach (['kayquitiii', 'lumampongbalagbag', 'barangay4'] as $slug) {
        $admin = User::where('email', $slug.'-admin@example.com')->firstOrFail();
        $nurse = User::where('email', $slug.'-nurse@example.com')->firstOrFail();
        expect($admin->isBarangayAdmin())->toBeTrue();
        expect($nurse->isNurse())->toBeTrue();
        expect($nurse->barangay_id)->toBe($admin->barangay_id);
        expect(Hash::check('password123', $admin->password))->toBeTrue();
    }
    $this->artisan('cvms:seed-dummy-administration')->assertSuccessful();

    expect(User::count())->toBe(7);
    expect(PopulationBackground::count())->toBe(36);
    expect(ChildProfile::withoutGlobalScope('not_archived')->whereNotNull('archived_at')->count())->toBe(3);
    expect(VaccinationRecord::archived()->count())->toBe(1);
    expect(AuditLog::archived()->count())->toBe(1);
    expect(ChildProfile::where('barangay_id', $outside->id)->count())->toBe(0);

    $superadmin = User::factory()->create(['role' => 'superadmin', 'roles' => ['superadmin']]);
    $this->actingAs($superadmin)->get(route('archives.index'))
        ->assertOk()->assertSee('Archived audit sample')->assertSee('Demo Report');
})->with([false, true]);
