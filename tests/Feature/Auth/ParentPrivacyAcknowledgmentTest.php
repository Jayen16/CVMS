<?php

use App\Models\User;
use App\Support\PrivacyNotice;

test('parents must acknowledge the privacy notice before accessing the app', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'roles' => ['parent'],
    ]);

    $response = $this->actingAs($parent)->get(route('dashboard'));

    $response->assertRedirect(route('privacy.acknowledgment', absolute: false));
});

test('parents can acknowledge the privacy notice and continue', function () {
    $parent = User::factory()->create([
        'role' => 'parent',
        'roles' => ['parent'],
    ]);

    $response = $this->actingAs($parent)->post(route('privacy.acknowledgment.store'), [
        'acknowledged' => '1',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    expect($parent->fresh())
        ->privacy_notice_version->toBe(PrivacyNotice::VERSION)
        ->privacy_acknowledged_at->not->toBeNull();
});

test('staff do not need the parent privacy acknowledgment', function () {
    $staff = User::factory()->create([
        'role' => 'nurse',
        'roles' => ['nurse'],
    ]);

    $this->actingAs($staff)->get(route('dashboard'))->assertOk();
});
