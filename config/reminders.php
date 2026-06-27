<?php

return [
    'enabled' => env('VACCINATION_REMINDERS_ENABLED', true),
    'lookahead_days' => (int) env('VACCINATION_REMINDER_LOOKAHEAD_DAYS', 7),
    'channels' => array_filter(array_map('trim', explode(',', (string) env('VACCINATION_REMINDER_CHANNELS', 'email')))),

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'sim800c' => [
            'command' => env('SIM800C_COMMAND'),
            'port' => env('SIM800C_PORT', 'COM3'),
            'baud_rate' => (int) env('SIM800C_BAUD_RATE', 9600),
            'timeout' => (int) env('SIM800C_TIMEOUT', 30),
        ],
    ],
];
