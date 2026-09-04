<?php

use App\Models\User;
use App\Notifications\AccountAccessNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, AccountAccessNotification::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, AccountAccessNotification::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('pending account setup link uses create password copy', function () {
    Notification::fake();

    $user = User::factory()->create([
        'invitation_accepted_at' => null,
    ]);

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, AccountAccessNotification::class, function ($notification) use ($user) {
        $this->get(route('password.reset', [
            'token' => $notification->token,
            'email' => $user->email,
        ]))
            ->assertOk()
            ->assertSee('Create password')
            ->assertDontSee('Reset password');

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, AccountAccessNotification::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        return true;
    });
});

test('changing the email in a reset link cannot reset the password', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'mackulangkaya@example.com', 'password' => 'original-password']);

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, AccountAccessNotification::class, function ($notification) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => 'mackulangkaya123@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('email');

        return true;
    });

    expect(Hash::check('original-password', $user->fresh()->password))->toBeTrue();
});
