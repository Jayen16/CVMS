<?php

use App\Services\Sms\SmsGatewayFactory;
use Illuminate\Support\Facades\Http;

test('UniSMS gateway sends the documented request payload and basic auth', function () {
    Http::fake([
        'https://unismsapi.com/api/sms' => Http::response([
            'message' => ['status' => 'sent', 'reference_id' => 'msg_test'],
        ], 201),
    ]);

    config([
        'reminders.sms.driver' => 'unisms',
        'reminders.sms.unisms.base_url' => 'https://unismsapi.com/api',
        'reminders.sms.unisms.secret_key' => 'sk_test_secret',
        'reminders.sms.unisms.sender_id' => 'RHU-CVMS',
    ]);

    app(SmsGatewayFactory::class)->make()->send('+639171234567', 'Vaccination reminder.');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://unismsapi.com/api/sms'
            && $request['recipient'] === '+639171234567'
            && $request['content'] === 'Vaccination reminder.'
            && $request['sender_id'] === 'RHU-CVMS'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_secret:'));
    });
});

test('UniSMS gateway fails clearly when configuration is incomplete', function () {
    config([
        'reminders.sms.driver' => 'unisms',
        'reminders.sms.unisms.base_url' => 'https://unismsapi.com/api',
        'reminders.sms.unisms.secret_key' => null,
        'reminders.sms.unisms.sender_id' => null,
    ]);

    expect(fn () => app(SmsGatewayFactory::class)->make()->send('+639171234567', 'Test'))
        ->toThrow(RuntimeException::class, 'UniSMS is not configured');
});
