<?php

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;
use App\Notifications\InAppNotification;
use App\Services\InAppNotificationService;

/* Announcement notification test disabled while the Announcement feature is hidden.
test('published announcements create one in-app notification for the intended audience', function () {
    $parent = User::factory()->create(['role' => 'parent']);
    $nurse = User::factory()->create(['role' => 'nurse']);
    $announcement = ClinicAnnouncement::create([
        'created_by' => $nurse->id,
        'title' => 'Child Health Day',
        'category' => 'campaign',
        'audience' => 'all',
        'starts_on' => now()->toDateString(),
        'message' => 'Bring your child for the clinic campaign.',
        'active' => true,
    ]);

    app(\App\Services\InAppNotificationService::class)->announcementPublished($announcement);
    app(\App\Services\InAppNotificationService::class)->announcementPublished($announcement);

    expect($parent->notifications()->count())->toBe(1)
        ->and($nurse->notifications()->count())->toBe(1)
        ->and($parent->notifications()->first()->data['title'])->toBe('Child Health Day');
});
*/

test('repeated syncs do not duplicate vaccination submission notifications', function () {
    $barangay = Barangay::create(['name' => 'Notification Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $parent = User::factory()->create(['role' => 'parent']);
    $vaccine = VaccineType::query()->firstOrFail();
    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Baby',
        'last_name' => 'Test',
        'birthdate' => '2024-01-01',
        'sex' => 'F',
        'guardian_name' => 'Parent Test',
    ]);
    $record = VaccinationRecord::create([
        'child_profile_id' => $child->id,
        'vaccine_type_id' => $vaccine->id,
        'recorded_by' => $parent->id,
        'submitted_by' => $parent->id,
        'dose_number' => 1,
        'source' => 'parent_portal',
        'verification_status' => 'pending',
        'administered_at' => '2026-01-01',
    ]);

    $service = app(InAppNotificationService::class);
    $service->vaccinationSubmitted($record);
    $service->vaccinationSubmitted($record->fresh());

    expect($nurse->notifications()->count())->toBe(1);
});

test('users can view and mark an in-app notification as read', function () {
    $user = User::factory()->create();
    $user->notify(new InAppNotification(
        key: 'test-notification',
        title: 'Test notification',
        body: 'This is a test.',
        actionUrl: route('notifications.index'),
    ));

    $notification = $user->notifications()->first();

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertSuccessful()
        ->assertSee('Test notification');

    $this->actingAs($user)
        ->get(route('notifications.read', $notification))
        ->assertRedirect(route('notifications.index'));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('invalid notification destinations safely return to the notifications page', function () {
    $user = User::factory()->create();
    $user->notify(new InAppNotification(
        key: 'invalid-destination',
        title: 'Invalid destination',
        body: 'This should not lead to a missing endpoint.',
        actionUrl: url('/endpoint'),
    ));

    $notification = $user->notifications()->first();

    $this->actingAs($user)
        ->get(route('notifications.read', $notification))
        ->assertRedirect(route('notifications.index'));
});
