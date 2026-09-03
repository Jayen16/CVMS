<?php

use App\Models\ClinicAnnouncement;
use App\Models\User;
use App\Notifications\InAppNotification;
use App\Services\InAppNotificationService;

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

    app(InAppNotificationService::class)->announcementPublished($announcement);
    app(InAppNotificationService::class)->announcementPublished($announcement);

    expect($parent->notifications()->count())->toBe(1)
        ->and($nurse->notifications()->count())->toBe(1)
        ->and($parent->notifications()->first()->data['title'])->toBe('Child Health Day');
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
