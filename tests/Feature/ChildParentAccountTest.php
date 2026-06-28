<?php

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('linking a new parent sends a password setup link', function () {
    Notification::fake();

    $barangay = Barangay::create(['name' => 'Invite Barangay']);
    $admin = User::factory()->create(['role' => 'admin']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);

    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Mia',
        'last_name' => 'Lopez',
        'birthdate' => now()->subMonths(9)->toDateString(),
        'sex' => 'female',
        'guardian_name' => 'Parent Lopez',
    ]);

    $this->actingAs($admin)
        ->post(route('children.parents.store', $child), [
            'name' => 'Parent Lopez',
            'email' => 'parent.lopez@example.com',
            'phone' => '09123456789',
            'relationship' => 'mother',
        ])
        ->assertRedirect(route('children.show', $child, absolute: false))
        ->assertSessionHas('status', 'Parent account linked to child profile. A password setup link was sent by email.');

    $parent = User::where('email', 'parent.lopez@example.com')->firstOrFail();

    expect($parent->isParent())->toBeTrue()
        ->and($parent->invitation_accepted_at)->toBeNull()
        ->and($child->parents()->whereKey($parent->id)->exists())->toBeTrue();

    Notification::assertSentTo($parent, ResetPassword::class);
});

test('resending setup link works for linked parent accounts that are still pending', function () {
    Notification::fake();

    $barangay = Barangay::create(['name' => 'Pending Barangay']);
    $admin = User::factory()->create(['role' => 'admin']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $parent = User::factory()->create([
        'role' => 'parent',
        'invitation_accepted_at' => null,
    ]);

    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Noah',
        'last_name' => 'Cruz',
        'birthdate' => now()->subMonths(10)->toDateString(),
        'sex' => 'male',
        'guardian_name' => $parent->name,
    ]);

    $child->parents()->attach($parent->id, ['relationship' => 'father']);

    $this->actingAs($admin)
        ->post(route('children.parents.setup-link', ['child' => $child, 'parent' => $parent]))
        ->assertRedirect(route('children.show', $child, absolute: false))
        ->assertSessionHas('status', 'Password setup link sent again.');

    Notification::assertSentTo($parent, ResetPassword::class);
});
