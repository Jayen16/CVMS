<?php

use App\Mail\VaccinationDueReminderMail;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationReminder;
use Illuminate\Support\Facades\Mail;

test('due vaccination reminders are sent once to linked parents', function () {
    Mail::fake();

    config([
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

    Mail::assertSent(VaccinationDueReminderMail::class, 1);

    expect(VaccinationReminder::count())->toBe(2)
        ->and(VaccinationReminder::where('channel', 'email')->where('status', 'sent')->exists())->toBeTrue()
        ->and(VaccinationReminder::where('channel', 'sms')->where('status', 'sent')->exists())->toBeTrue();

    $this->artisan('vaccinations:send-reminders')->assertSuccessful();

    Mail::assertSent(VaccinationDueReminderMail::class, 1);
    expect(VaccinationReminder::count())->toBe(2);
});
