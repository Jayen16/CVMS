<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'system.instance_type' => 'central',
        'reminders.sms.driver' => 'unisms',
        'reminders.sms.unisms.base_url' => 'https://unismsapi.com/api',
        'reminders.sms.unisms.secret_key' => 'sk_test_secret',
        'reminders.sms.unisms.sender_id' => 'Unisoft',
    ]);
    Http::preventStrayRequests();
});

test('SMS activation and reset send a numeric OTP immediately and accept it for verification', function (string $mode) {
    $user = User::factory()->create([
        'phone' => '09397614952', 'role' => 'parent', 'roles' => ['parent'],
        'is_active' => $mode === 'reset',
        'invitation_accepted_at' => $mode === 'reset' ? now() : null,
    ]);
    $code = null;
    Http::fake(function ($request) use (&$code) {
        $code = $request['content'];
        expect($request->url())->toBe('https://unismsapi.com/api/sms');
        expect($request['recipient'])->toBe('+639397614952');
        expect($code)->toMatch('/\A[0-9]{10}\z/');

        return Http::response(['message' => ['status' => 'pending', 'reference_id' => 'msg_otp']], 201);
    });
    $sendRoute = $mode === 'activation' ? 'account.activation.otp.send' : 'password.otp.send';
    $verifyRoute = $mode === 'activation' ? 'account.activation.otp.verify' : 'password.otp.verify';

    $this->post(route($sendRoute), ['identifier' => $user->phone, 'mode' => $mode])
        ->assertSessionHas('otp_sent', true)->assertSessionMissing('toast_error');
    Http::assertSentCount(1);
    $key = 'password-otp:'.hash('sha256', $user->phone);
    expect(Hash::check($code, Cache::get($key)['code']))->toBeTrue();

    $this->post(route($verifyRoute), [
        'identifier' => $user->phone, 'mode' => $mode, 'code' => $code,
        'password' => 'Example!Pass12345', 'password_confirmation' => 'Example!Pass12345',
    ])->assertSessionHasNoErrors()->assertRedirect(route('login'));
    expect($user->fresh()->is_active)->toBeTrue();
    expect(Hash::check('Example!Pass12345', $user->fresh()->password))->toBeTrue();
    expect(Cache::has($key))->toBeFalse();
})->with(['activation', 'reset']);

test('invalid mobile numbers are rejected before requesting an SMS', function () {
    Http::fake();
    $this->from(route('account.activation'))->post(route('account.activation.otp.send'), [
        'identifier' => '093976149526', 'mode' => 'activation',
    ])->assertSessionHasErrors('identifier')->assertSessionMissing('otp_sent');
    Http::assertNothingSent();
});

test('a rejected SMS leaves no usable OTP and allows an immediate retry', function () {
    $user = User::factory()->create(['phone' => '09397614952', 'invitation_accepted_at' => null]);
    Http::fakeSequence()
        ->push(['errors' => ['recipient' => ['Must be a valid Philippine phone number']]], 422)
        ->push(['message' => ['status' => 'pending', 'reference_id' => 'msg_retry']], 201);

    $data = ['identifier' => $user->phone, 'mode' => 'activation'];
    $this->post(route('account.activation.otp.send'), $data)
        ->assertSessionHas('toast_error')->assertSessionHas('otp_identifier', $user->phone);
    expect(Cache::has('password-otp:'.hash('sha256', $user->phone)))->toBeFalse();
    $this->post(route('account.activation.otp.send'), $data)->assertSessionHas('otp_sent', true);
    Http::assertSentCount(2);
});
