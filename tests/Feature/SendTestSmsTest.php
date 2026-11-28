<?php

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Models\VaccinationReminder;
use App\Models\VaccineSchedule;
use App\Models\VaccineScheduleVersion;
use App\Models\VaccineType;
use App\Services\ImmunizationSuggestionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-28 10:00:00', 'Asia/Manila'));
    Http::preventStrayRequests();
    Http::fake([
        'https://sms.example.test/sms' => fn () => Http::response([], config('testing.sms_status', 201)),
    ]);
    Queue::fake();
    config([
        'system.instance_type' => 'central',
        'reminders.sms.driver' => 'unisms',
        'reminders.sms.unisms.base_url' => 'https://sms.example.test',
        'reminders.sms.unisms.secret_key' => 'test-key',
        'reminders.sms.unisms.sender_id' => 'TEST',
    ]);
    $region = Region::create(['name' => 'Test Region']);
    $province = Province::create(['name' => 'Cavite', 'region_id' => $region->id]);
    $municipality = Municipality::create(['name' => 'Indang', 'province_id' => $province->id]);
    Barangay::create(['name' => 'Bancod', 'municipality_id' => $municipality->id]);
    $version = VaccineScheduleVersion::create([
        'version_code' => 'sms-test', 'name' => 'SMS test schedule',
        'effective_date' => '2026-07-01', 'status' => 'active',
    ]);
    foreach (config('immunization.vaccines') as $data) {
        $vaccine = VaccineType::firstOrCreate(['code' => $data['code']], $data + ['active' => true]);
        foreach (config('immunization.routine_schedule.'.$vaccine->code) as $dose) {
            VaccineSchedule::create([
                'vaccine_schedule_version_id' => $version->id, 'vaccine_type_id' => $vaccine->id,
                'dose_number' => $dose['dose'], 'label' => $dose['label'], 'active' => true,
                'age_days' => $dose['age']['days'] ?? 0, 'age_weeks' => $dose['age']['weeks'] ?? 0,
                'age_months' => $dose['age']['months'] ?? 0, 'age_years' => $dose['age']['years'] ?? 0,
            ]);
        }
    }
});

test('command sends the real parent reminder immediately and does not duplicate it', function () {
    $this->artisan('cvms:send-test-sms')->assertSuccessful();
    $child = ChildProfile::sole();
    $suggestion = app(ImmunizationSuggestionService::class)->suggestNextDose($child);
    expect($child->parents()->sole()->phone)->toBe('09760149526');
    expect($suggestion['due_at']->toDateString())->toBe('2026-11-29');
    expect(VaccinationReminder::sole()->status)->toBe('sent');
    $message = 'Reminder: Baby Demo is due for '.$suggestion['vaccine_name'].' dose 1 on Nov 29, 2026. Please visit the clinic.';
    Http::assertSent(fn ($request) => $request['recipient'] === '+639760149526' && $request['content'] === $message);
    $this->artisan('cvms:send-test-sms')->assertSuccessful();
    Http::assertSentCount(1);
    expect(ChildProfile::count())->toBe(1);
    Queue::assertNothingPushed();
});

test('prepare only assigns a child without sending or queuing SMS', function () {
    $this->artisan('cvms:send-test-sms', ['--prepare-only' => true])->assertSuccessful();
    expect(ChildProfile::sole()->parents()->sole()->phone)->toBe('09760149526');
    expect(VaccinationReminder::count())->toBe(0);
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

test('provider failures are recorded without automatic retry', function () {
    config(['testing.sms_status' => 422]);
    $this->artisan('cvms:send-test-sms')->assertFailed();
    expect(VaccinationReminder::sole()->status)->toBe('failed');
    Http::assertSentCount(1);
    Queue::assertNothingPushed();
});

test('existing parent details are preserved and unsafe phone ownership is rejected', function () {
    $parent = User::factory()->create(['phone' => '09760149526', 'role' => 'parent', 'roles' => ['parent'], 'is_active' => true]);
    $before = $parent->fresh()->toArray();
    $this->artisan('cvms:send-test-sms', ['--prepare-only' => true])->assertSuccessful();
    expect($parent->fresh()->toArray())->toBe($before);
    $parent->forceFill(['role' => 'nurse', 'roles' => ['nurse']])->save();
    $this->artisan('cvms:send-test-sms')->assertFailed();
    Http::assertNothingSent();
});

test('command refuses a logging gateway instead of claiming SMS was sent', function () {
    config(['reminders.sms.driver' => 'log']);
    $this->artisan('cvms:send-test-sms')->assertFailed();
    expect(ChildProfile::count())->toBe(0);
    Http::assertNothingSent();
});
