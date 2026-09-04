<?php

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use App\Notifications\AccountAccessNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

test('linking a new parent sends a password setup link', function () {
    Notification::fake();

    $barangay = Barangay::create(['name' => 'Invite Barangay']);
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

    $this->actingAs($nurse)
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

    Notification::assertSentTo($parent, AccountAccessNotification::class);
});

test('linking a phone-only parent sends a password setup link by SMS', function () {
    Notification::fake();
    Log::spy();

    $barangay = Barangay::create(['name' => 'Phone Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);

    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Lia',
        'last_name' => 'Santos',
        'birthdate' => now()->subMonths(7)->toDateString(),
        'sex' => 'female',
        'guardian_name' => 'Parent Santos',
    ]);

    $this->actingAs($nurse)
        ->post(route('children.parents.store', $child), [
            'name' => 'Parent Santos',
            'phone' => '09179990000',
            'relationship' => 'mother',
        ])
        ->assertRedirect(route('children.show', $child, absolute: false))
        ->assertSessionHas('status', 'Parent account linked to child profile. A password setup link was sent successfully by SMS.');

    $parent = User::where('phone', '09179990000')->firstOrFail();

    expect($parent->email)->toBeNull()
        ->and($parent->invitation_accepted_at)->toBeNull()
        ->and($child->parents()->whereKey($parent->id)->exists())->toBeTrue();

    Notification::assertNothingSent();
    Log::shouldHaveReceived('info')->with('SMS reminder logged.', Mockery::on(function (array $context): bool {
        return $context['recipient'] === '+639177999000'
            && str_contains($context['message'], 'CVMS password setup link:');
    }));
});

test('linking another parent keeps the first parent linked', function () {
    Notification::fake();

    $barangay = Barangay::create(['name' => 'Multiple Parents Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Ana',
        'last_name' => 'Cruz',
        'birthdate' => now()->subMonths(9)->toDateString(),
        'sex' => 'female',
        'guardian_name' => 'Ana Guardian',
    ]);

    $this->actingAs($nurse)->post(route('children.parents.store', $child), [
        'name' => 'First Parent',
        'email' => 'first.parent@example.com',
        'relationship' => 'mother',
    ]);

    $this->actingAs($nurse)->post(route('children.parents.store', $child), [
        'name' => 'Second Parent',
        'email' => 'second.parent@example.com',
        'relationship' => 'father',
    ])->assertRedirect(route('children.show', $child, absolute: false));

    $child->load('parents');

    expect($child->parents)->toHaveCount(2)
        ->and($child->parents->pluck('email')->all())->toContain('first.parent@example.com', 'second.parent@example.com')
        ->and($child->parents->firstWhere('email', 'first.parent@example.com')->pivot->relationship)->toBe('mother')
        ->and($child->parents->firstWhere('email', 'second.parent@example.com')->pivot->relationship)->toBe('father');
});

test('resending setup link works for linked parent accounts that are still pending', function () {
    Notification::fake();

    $barangay = Barangay::create(['name' => 'Pending Barangay']);
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

    $this->actingAs($nurse)
        ->post(route('children.parents.setup-link', ['child' => $child, 'parent' => $parent]))
        ->assertRedirect(route('children.show', $child, absolute: false))
        ->assertSessionHas('status', 'Password setup link sent again.');

    Notification::assertSentTo($parent, AccountAccessNotification::class);
});

test('resending setup link returns json for ajax requests', function () {
    Notification::fake();

    $barangay = Barangay::create(['name' => 'Ajax Barangay']);
    $nurse = User::factory()->create(['role' => 'nurse', 'barangay_id' => $barangay->id]);
    $parent = User::factory()->create([
        'role' => 'parent',
        'invitation_accepted_at' => null,
    ]);
    $child = ChildProfile::create([
        'barangay_id' => $barangay->id,
        'created_by' => $nurse->id,
        'first_name' => 'Mia',
        'last_name' => 'Cruz',
        'birthdate' => now()->subMonths(10)->toDateString(),
        'sex' => 'female',
        'guardian_name' => $parent->name,
    ]);
    $child->parents()->attach($parent->id, ['relationship' => 'mother']);

    $this->actingAs($nurse)
        ->withHeader('Accept', 'application/json')
        ->post(route('children.parents.setup-link', ['child' => $child, 'parent' => $parent]))
        ->assertOk()
        ->assertJson(['message' => 'Password setup link sent again.']);
});
