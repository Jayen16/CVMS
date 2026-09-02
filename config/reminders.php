<?php

return [
    'enabled' => env('VACCINATION_REMINDERS_ENABLED', true),
    'lookahead_days' => (int) env('VACCINATION_REMINDER_LOOKAHEAD_DAYS', 7),
    'channels' => array_filter(array_map('trim', explode(',', (string) env('VACCINATION_REMINDER_CHANNELS', 'email')))),

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'api' => [
            'url' => env('SMS_API_URL'),
            'token' => env('SMS_API_TOKEN'),
            'from' => env('SMS_API_FROM'),
            'timeout' => (int) env('SMS_API_TIMEOUT', 15),
        ],
    ],
];
