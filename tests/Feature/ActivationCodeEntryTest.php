<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

test('an existing activation code can be entered without requesting or sending another code', function () {
    config(['system.instance_type' => 'central']);
    Http::preventStrayRequests();
    Http::fake();
    Notification::fake();
    $user = User::factory()->create([
        'phone' => '09985301020', 'role' => 'parent', 'roles' => ['parent'],
        'is_active' => false, 'invitation_accepted_at' => null,
    ]);
    $key = 'password-otp:'.hash('sha256', $user->phone);
    Cache::put($key, [
        'user_id' => $user->id, 'code' => Hash::make('981235'),
        'attempts' => 0, 'activation' => true, 'mode' => 'activation',
    ], now()->addMinutes(10));

    $this->get(route('account.activation.code', ['identifier' => $user->phone]))
        ->assertOk()->assertSee('Activation code')->assertSee($user->phone);
    $this->post(route('account.activation.otp.verify'), [
        'identifier' => $user->phone, 'mode' => 'activation', 'code' => '981235',
        'password' => 'Example!Pass12345', 'password_confirmation' => 'Example!Pass12345',
    ])->assertRedirect(route('login'));

    expect($user->fresh()->is_active)->toBeTrue();
    expect(Hash::check('Example!Pass12345', $user->fresh()->password))->toBeTrue();
    expect(Cache::has($key))->toBeFalse();
    Http::assertNothingSent();
    Notification::assertNothingSent();
});

test('direct activation entry does not bypass an expired or missing code', function () {
    config(['system.instance_type' => 'central']);
    $this->post(route('account.activation.otp.verify'), [
        'identifier' => '09985301020', 'mode' => 'activation', 'code' => '981235',
        'password' => 'Example!Pass12345', 'password_confirmation' => 'Example!Pass12345',
    ])->assertStatus(422);
});
