<?php

use App\Jobs\SendVaccinationReminder;
use App\Mail\VaccinationDueReminderMail;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationReminder;
use App\Services\ImmunizationSuggestionService;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsGatewayFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->mock(ImmunizationSuggestionService::class, function ($mock) {
        $mock->shouldReceive('suggestNextDose')->andReturn([
            'due_at' => Carbon::today()->addDays(7),
            'vaccine_name' => 'Test vaccine', 'dose_number' => 1,
        ]);
    });
});

test('due vaccination reminders are sent once to linked parents', function () {
    Mail::fake();

    config([
        'system.instance_type' => 'central',
        'reminders.enabled' => true,
        'reminders.lookahead_days' => 7,
        'reminders.channels' => ['email', 'sms'],
        'reminders.sms.driver' => 'log',
    ]);

    $barangay = Barangay::create(['name' => 'Reminder Barangay']);
    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => '+639171234567',
    ]);
    $nurse = User::factory()->create([
        'role' => 'nurse',
        'barangay_id' => $barangay->id,
    ]);

    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Lia',
        'last_name' => 'Santos',
        'birthdate' => now()->toDateString(),
        'sex' => 'female',
        'guardian_name' => $parent->name,
        'guardian_contact' => $parent->phone,
    ]);

    $child->parents()->attach($parent->id, ['relationship' => 'mother']);

    $this->artisan('vaccinations:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();

    expect(VaccinationReminder::count())->toBe(1)
        ->and(VaccinationReminder::where('channel', 'sms')->where('status', 'sent')->exists())->toBeTrue()
        ->and(VaccinationReminder::where('channel', 'email')->exists())->toBeFalse();

    $this->artisan('vaccinations:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();
    expect(VaccinationReminder::count())->toBe(1);
});

test('email is used when a linked parent has no phone number', function () {
    Mail::fake();

    config([
        'system.instance_type' => 'central',
        'reminders.enabled' => true,
        'reminders.lookahead_days' => 7,
        'reminders.channels' => ['email', 'sms'],
        'reminders.sms.driver' => 'log',
    ]);

    $barangay = Barangay::create(['name' => 'Email Reminder Barangay']);
    $parent = User::factory()->create(['role' => 'parent', 'phone' => null]);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Email',
        'last_name' => 'Reminder',
        'birthdate' => now()->toDateString(),
        'sex' => 'male',
        'guardian_name' => $parent->name,
        'guardian_contact' => null,
    ]);
    $child->parents()->attach($parent->id, ['relationship' => 'father']);

    $this->artisan('vaccinations:send-reminders')->assertSuccessful();

    Mail::assertSent(VaccinationDueReminderMail::class, 1);
    expect(VaccinationReminder::count())->toBe(1)
        ->and(VaccinationReminder::first()->channel)->toBe('email');
});

test('the reminder scheduler dispatches a queued delivery job', function () {
    Queue::fake();

    config([
        'system.instance_type' => 'central',
        'reminders.enabled' => true,
        'reminders.lookahead_days' => 7,
        'reminders.channels' => ['sms'],
    ]);

    $barangay = Barangay::create(['name' => 'Queued Reminder Barangay']);
    $parent = User::factory()->create(['role' => 'parent', 'phone' => '+639171234567']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Queued',
        'last_name' => 'Reminder',
        'birthdate' => now()->toDateString(),
        'sex' => 'female',
        'guardian_name' => $parent->name,
    ]);
    $child->parents()->attach($parent->id, ['relationship' => 'mother']);

    $this->artisan('vaccinations:send-reminders')->assertSuccessful();

    Queue::assertPushed(SendVaccinationReminder::class, function (SendVaccinationReminder $job) use ($child, $parent): bool {
        return $job->childId === $child->id
            && $job->parentId === $parent->id
            && $job->channel === 'sms';
    });
    expect(VaccinationReminder::count())->toBe(0);
});

test('central reminders exclude facility-owned children', function () {
    Queue::fake();

    config([
        'system.instance_type' => 'central',
        'reminders.enabled' => true,
        'reminders.lookahead_days' => 7,
        'reminders.channels' => ['sms'],
    ]);

    $barangay = Barangay::create(['name' => 'Central Ownership Barangay']);
    $parent = User::factory()->create(['role' => 'parent', 'phone' => '+639171234567']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);

    foreach ([['name' => 'Central Child', 'facility_uuid' => null], ['name' => 'Facility Child', 'facility_uuid' => (string) Str::uuid()]] as $data) {
        [$firstName, $lastName] = explode(' ', $data['name']);
        $child = ChildProfile::create([
            'barangay_id' => $barangay->id,
            'facility_uuid' => $data['facility_uuid'],
            'created_by' => $nurse->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'birthdate' => now()->toDateString(),
            'sex' => 'female',
            'guardian_name' => $parent->name,
        ]);
        $child->parents()->attach($parent->id, ['relationship' => 'mother']);
    }

    $this->artisan('vaccinations:send-reminders')->assertSuccessful();

    Queue::assertPushed(SendVaccinationReminder::class, 1);
    Queue::assertPushed(SendVaccinationReminder::class, fn ($job): bool => $job->childId === ChildProfile::where('first_name', 'Central')->value('id'));
});

test('parents receive distinct seven three and one day reminders without repeated deliveries', function (string $channel) {
    Mail::fake();
    config([
        'system.instance_type' => 'central',
        'reminders.enabled' => true,
        'reminders.lookahead_days' => 7,
        'reminders.channels' => ['email', 'sms'],
        'reminders.sms.driver' => 'log',
    ]);
    $this->travelTo(Carbon::parse('2026-11-01 08:00:00'));
    $barangay = Barangay::create(['name' => 'Staged Reminders']);
    $parent = User::factory()->create([
        'role' => 'parent',
        'phone' => $channel === 'sms' ? '+639171234567' : null,
    ]);
    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'first_name' => 'Staged', 'last_name' => 'Reminder',
        'birthdate' => '2026-10-01', 'sex' => 'female',
        'guardian_name' => $parent->name,
    ]);
    $child->parents()->attach($parent->id, ['relationship' => 'mother']);
    $this->mock(ImmunizationSuggestionService::class, function ($mock) {
        $mock->shouldReceive('suggestNextDose')->andReturn([
            'due_at' => Carbon::parse('2026-11-09'),
            'vaccine_name' => 'Test vaccine', 'dose_number' => 1,
        ]);
    });

    // Eight days before: outside the reminder window.
    $this->artisan('vaccinations:send-reminders')->assertSuccessful();
    expect(VaccinationReminder::count())->toBe(0);

    foreach ([2 => 'initial', 6 => 'three_days', 8 => 'one_day'] as $day => $stage) {
        $this->travelTo(Carbon::parse('2026-11-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT).' 08:00:00'));
        $this->artisan('vaccinations:send-reminders')->assertSuccessful();
        $this->artisan('vaccinations:send-reminders')->assertSuccessful();
        expect(VaccinationReminder::where('reminder_stage', $stage)->where('channel', $channel)->where('status', 'sent')->count())->toBe(1);
    }

    // Re-executing a delivered job must not reset its sent status.
    app()->call([new SendVaccinationReminder(
        (string) $child->id, (string) $parent->id, 'Test vaccine', 1,
        '2026-11-09', $channel, 'one_day',
    ), 'handle']);

    // Due and overdue dates do not add another copy of the final stage.
    foreach ([9, 10] as $day) {
        $this->travelTo(Carbon::parse('2026-11-'.$day.' 08:00:00'));
        $this->artisan('vaccinations:send-reminders')->assertSuccessful();
    }
    expect(VaccinationReminder::count())->toBe(3);
    if ($channel === 'email') {
        Mail::assertSent(VaccinationDueReminderMail::class, 3);
    } else {
        Mail::assertNothingSent();
    }

})->with(['email', 'sms']);

test('past due schedules are excluded and old queued reminders cannot be delivered', function (string $channel, string $dueDate) {
    Mail::fake();
    Queue::fake();
    $gateway = Mockery::mock(SmsGateway::class);
    $gateway->shouldNotReceive('send');
    $this->mock(SmsGatewayFactory::class, function ($mock) use ($gateway) {
        $mock->shouldReceive('make')->zeroOrMoreTimes()->andReturn($gateway);
    });
    config([
        'system.instance_type' => 'central', 'reminders.enabled' => true,
        'reminders.lookahead_days' => 7, 'reminders.channels' => [$channel],
    ]);
    $this->travelTo(Carbon::parse('2026-11-28 08:00:00'));
    $barangay = Barangay::create(['name' => 'Old Schedule']);
    $parent = User::factory()->create(['role' => 'parent', 'phone' => '+639171234567']);
    $child = ChildProfile::create([
        'barangay_id' => $barangay->id, 'first_name' => 'Old', 'last_name' => 'Schedule',
        'birthdate' => '2024-01-01', 'sex' => 'female', 'guardian_name' => $parent->name,
    ]);
    $child->parents()->attach($parent->id, ['relationship' => 'mother']);
    $this->mock(ImmunizationSuggestionService::class, function ($mock) use ($dueDate) {
        $mock->shouldReceive('suggestNextDose')->andReturn([
            'due_at' => Carbon::parse($dueDate), 'vaccine_name' => 'Test vaccine', 'dose_number' => 1,
        ]);
    });

    $this->artisan('vaccinations:send-reminders')->assertSuccessful();
    Queue::assertNothingPushed();

    app()->call([new SendVaccinationReminder(
        (string) $child->id, (string) $parent->id, 'Test vaccine', 1, $dueDate, $channel, 'one_day',
    ), 'handle']);
    Mail::assertNothingSent();
    expect(VaccinationReminder::count())->toBe(0);
})->with(['email', 'sms'])->with([
    '2024-06-01', '2026-11-27', '2026-11-28',
    '2026-11-30', '2026-12-02', '2026-12-03', '2026-12-04', '2026-12-06',
]);

test('a missed three day reminder is not queued two days before the due date', function () {
    Queue::fake();
    config(['system.instance_type' => 'central', 'reminders.enabled' => true, 'reminders.lookahead_days' => 7, 'reminders.channels' => ['email']]);
    $this->travelTo(Carbon::parse('2026-11-07 08:00:00'));
    $barangay = Barangay::create(['name' => 'Downtime Reminders']);
    $parent = User::factory()->create(['role' => 'parent', 'phone' => null]);
    $child = ChildProfile::create([
        'barangay_id' => $barangay->id, 'first_name' => 'Downtime', 'last_name' => 'Child',
        'birthdate' => '2026-10-01', 'sex' => 'male', 'guardian_name' => $parent->name,
    ]);
    $child->parents()->attach($parent->id, ['relationship' => 'father']);
    $this->mock(ImmunizationSuggestionService::class, function ($mock) {
        $mock->shouldReceive('suggestNextDose')->andReturn([
            'due_at' => Carbon::parse('2026-11-09'),
            'vaccine_name' => 'Test vaccine', 'dose_number' => 1,
        ]);
    });
    $this->artisan('vaccinations:send-reminders')->assertSuccessful();
    Queue::assertNothingPushed();
});

test('queued reminders only deliver on the matching stage day', function (string $stage) {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-11-28 08:00:00'));
    $barangay = Barangay::create(['name' => 'Expired Stage']);
    $parent = User::factory()->create(['role' => 'parent']);
    $child = ChildProfile::create([
        'barangay_id' => $barangay->id, 'first_name' => 'Expired', 'last_name' => 'Stage',
        'birthdate' => '2026-10-01', 'sex' => 'female', 'guardian_name' => $parent->name,
    ]);
    // Three days remain: an old seven-day job and an early one-day job
    // must both be rejected even though today is a valid reminder day.
    app()->call([new SendVaccinationReminder(
        (string) $child->id, (string) $parent->id, 'Test vaccine', 1,
        '2026-12-01', 'email', $stage,
    ), 'handle']);
    Mail::assertNothingSent();
    expect(VaccinationReminder::count())->toBe(0);
})->with(['initial', 'one_day']);
