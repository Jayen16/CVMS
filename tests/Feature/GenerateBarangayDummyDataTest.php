<?php

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Models\VaccineInventoryItem;
use App\Models\VaccineInventoryTransaction;
use App\Models\VaccineSchedule;
use App\Models\VaccineType;
use App\Services\DummyVaccinationTimeline;
use App\Services\PredictiveAnalyticsService;
use App\Services\VaccineScheduleVersionResolver;
use Illuminate\Database\Eloquent\Model;

test('dummy stock randomization covers shortage and surplus ranges and preserves other inventory', function (bool $dryRun) {
    $region = Region::create(['name' => 'Dummy Region']);
    $province = Province::create(['name' => 'Dummy Province', 'region_id' => $region->id]);
    $municipality = Municipality::create(['name' => 'Indang', 'province_id' => $province->id]);
    $barangay = Barangay::create(['name' => 'Dummy Barangay', 'municipality_id' => $municipality->id]);
    $outside = Barangay::create(['name' => 'Outside Indang']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $items = collect();
    foreach (range(1, 7) as $index) {
        $vaccine = VaccineType::create(['name' => 'Dummy Vaccine '.$index, 'code' => 'DUMMY-'.$index, 'active' => true]);
        $item = VaccineInventoryItem::create([
            'item_code' => ($index === 6 ? 'real-' : 'dummy-').$index,
            'barangay_id' => $index === 7 ? $outside->id : $barangay->id,
            'vaccine_type_id' => $vaccine->id,
            'received_at' => today()->subYears(5),
            'expiry_date' => today()->addYears(2),
            'notes' => $index === 6 ? 'Real stock' : 'CVMS dummy population v1 - test stock',
        ]);
        VaccineInventoryTransaction::create([
            'barangay_id' => $item->barangay_id, 'vaccine_type_id' => $vaccine->id,
            'vaccine_inventory_item_id' => $item->id, 'recorded_by' => $nurse->id,
            'transaction_type' => 'receipt', 'movement' => 'in', 'quantity' => 900,
            'transaction_date' => today()->subYears(5),
        ]);
        $items->push($item);
    }
    // Multiple dummy batches of the same vaccine must share one target balance.
    $extra = $items->first()->replicate();
    $extra->item_code = 'dummy-extra';
    $extra->save();
    $originalTransactions = VaccineInventoryTransaction::orderBy('id')->get()->toArray();
    $options = ['--randomize-stock-only' => true, '--dry-run' => $dryRun];

    $this->artisan('cvms:generate-dummy-data', $options)->assertSuccessful();

    expect(VaccineInventoryTransaction::whereIn('id', array_column($originalTransactions, 'id'))->orderBy('id')->get()->toArray())
        ->toBe($originalTransactions);
    expect($items[5]->availableStock())->toBe(900)
        ->and($items[6]->availableStock())->toBe(900);
    if ($dryRun) {
        expect(VaccineInventoryTransaction::count())->toBe(7);
        foreach ($items->take(5) as $item) {
            expect($item->availableStock())->toBe(900);
        }
    } else {
        $balances = $items->take(5)->map(fn ($item) => $item->availableStock()
            + ($item->vaccine_type_id === $extra->vaccine_type_id ? $extra->availableStock() : 0))->sort()->values();
        foreach ([[0, 0], [1, 5], [6, 20], [40, 150], [400, 1200]] as $index => [$minimum, $maximum]) {
            expect($balances[$index])->toBeBetween($minimum, $maximum);
        }
        expect(VaccineInventoryTransaction::whereNotIn('id', array_column($originalTransactions, 'id'))
            ->whereDate('transaction_date', '!=', today())->count())->toBe(0);
    }
})->with([false, true]);

test('generated timelines produce distinct demand at each available forecast period', function () {
    $this->travelTo(now()->setDate(2026, 11, 27)->startOfDay());
    $barangay = Barangay::create(['name' => 'Forecast Timeline Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $version = app(VaccineScheduleVersionResolver::class)->activeVersion();
    $schedules = VaccineSchedule::where('vaccine_schedule_version_id', $version->id)
        ->where('active', true)->get();

    Model::withoutEvents(function () use ($barangay, $nurse, $schedules) {
        foreach (range(0, 49) as $index) {
            $plan = app(DummyVaccinationTimeline::class)->plan($schedules, $index);
            $child = ChildProfile::create([
                'barangay_id' => $barangay->id, 'created_by' => $nurse->id,
                'first_name' => 'Forecast', 'last_name' => 'Child '.$index,
                'birthdate' => $plan['birthdate'], 'sex' => 'female', 'guardian_name' => 'Guardian',
            ]);
            foreach ($plan['schedules'] as $i => $schedule) {
                $child->vaccinations()->create([
                    'vaccine_type_id' => $schedule->vaccine_type_id, 'dose_number' => $schedule->dose_number,
                    'administered_at' => $plan['dates'][$i], 'recorded_by' => $nurse->id,
                    'source' => 'barangay_clinic', 'verification_status' => 'verified',
                ]);
            }
        }
    });

    $previous = null;
    foreach ([1, 3, 6, 12] as $months) {
        $demand = app(PredictiveAnalyticsService::class)->vaccineDemand($nurse, $months, $version);
        if ($previous !== null) {
            expect($demand->sum('estimated_demand'))->toBeGreaterThan($previous->sum('estimated_demand'));
            $changed = $demand->filter(fn ($row) => $row['projected_balance']
                < $previous->firstWhere('vaccine.id', $row['vaccine']->id)['projected_balance']);
            expect($changed->count())->toBeGreaterThanOrEqual(3);
        }
        $previous = $demand;
    }
});
