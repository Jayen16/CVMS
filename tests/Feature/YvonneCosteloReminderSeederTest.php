<?php

use App\Jobs\SendVaccinationReminder;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineSchedule;
use App\Models\VaccineScheduleVersion;
use App\Models\VaccineType;
use App\Services\ImmunizationSuggestionService;
use Database\Seeders\YvonneCosteloReminderSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

test('costelo fixture reuses the registered account and queues the one day IPV reminder at eight', function () {
    $this->travelTo(Carbon::parse('2026-11-28 02:00:00'));
    config(['system.instance_type' => 'central', 'queue.default' => 'database']);
    Queue::fake();
    $region = Region::create(['name' => 'Test Region']);
    $province = Province::create(['name' => 'Cavite', 'region_id' => $region->id]);
    $municipality = Municipality::create(['name' => 'Indang', 'province_id' => $province->id]);
    Barangay::create(['name' => 'Bancod', 'municipality_id' => $municipality->id]);
    $parent = User::factory()->create(['phone' => '09156839837', 'role' => 'parent', 'roles' => ['parent']]);
    $password = $parent->password;
    $version = VaccineScheduleVersion::create([
        'version_code' => 'costelo-test', 'name' => 'Test schedule',
        'effective_date' => '2030-07-01', 'status' => 'active',
    ]);
    foreach (config('immunization.vaccines') as $vaccineData) {
        $vaccine = VaccineType::firstOrCreate(['code' => $vaccineData['code']], $vaccineData + ['active' => true]);
        foreach (config('immunization.routine_schedule.'.$vaccine->code) as $dose) {
            VaccineSchedule::create([
                'vaccine_schedule_version_id' => $version->id, 'vaccine_type_id' => $vaccine->id,
                'dose_number' => $dose['dose'], 'label' => $dose['label'], 'active' => true,
                'age_days' => $dose['age']['days'] ?? 0, 'age_weeks' => $dose['age']['weeks'] ?? 0,
                'age_months' => $dose['age']['months'] ?? 0, 'age_years' => $dose['age']['years'] ?? 0,
            ]);
        }
    }

    $this->seed(YvonneCosteloReminderSeeder::class);
    $count = VaccinationRecord::count();
    $this->seed(YvonneCosteloReminderSeeder::class);

    $child = ChildProfile::sole();
    $suggestion = app(ImmunizationSuggestionService::class)->suggestNextDose($child);
    expect($parent->fresh()->name)->toBe('Yvonne Costelo')
        ->and($parent->fresh()->password)->toBe($password)
        ->and(User::count())->toBe(1)
        ->and($child->full_name)->toBe('Girl Costelo')
        ->and($child->birthdate->toDateString())->toBe('2026-08-23')
        ->and($child->parents()->sole()->id)->toBe($parent->id)
        ->and(VaccinationRecord::count())->toBe($count)
        ->and($suggestion['vaccine_code'])->toBe('ipv')
        ->and($suggestion['dose_number'])->toBe(1)
        ->and($suggestion['due_at']->toDateString())->toBe('2026-11-29');
    Queue::assertPushed(SendVaccinationReminder::class, fn ($job) => $job->parentId === $parent->id && $job->channel === 'sms'
        && $job->reminderStage === 'one_day'
        && $job->delay->format('Y-m-d H:i:s') === '2026-11-28 08:00:00');
});
