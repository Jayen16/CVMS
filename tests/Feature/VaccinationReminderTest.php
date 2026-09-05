<?php

use App\Mail\VaccinationDueReminderMail;
use App\Jobs\SendVaccinationReminder;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationReminder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

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
        'birthdate' => now()->subMonth()->toDateString(),
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
        'birthdate' => now()->subMonth()->toDateString(),
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
        'birthdate' => now()->subMonth()->toDateString(),
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

    foreach ([['name' => 'Central Child', 'facility_uuid' => null], ['name' => 'Facility Child', 'facility_uuid' => (string) \Illuminate\Support\Str::uuid()]] as $data) {
        [$firstName, $lastName] = explode(' ', $data['name']);
        $child = ChildProfile::create([
            'barangay_id' => $barangay->id,
            'facility_uuid' => $data['facility_uuid'],
            'created_by' => $nurse->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'birthdate' => now()->subMonth()->toDateString(),
            'sex' => 'female',
            'guardian_name' => $parent->name,
        ]);
        $child->parents()->attach($parent->id, ['relationship' => 'mother']);
    }

    $this->artisan('vaccinations:send-reminders')->assertSuccessful();

    Queue::assertPushed(\App\Jobs\SendVaccinationReminder::class, 1);
    Queue::assertPushed(\App\Jobs\SendVaccinationReminder::class, fn ($job): bool => $job->childId === ChildProfile::where('first_name', 'Central')->value('id'));
});
