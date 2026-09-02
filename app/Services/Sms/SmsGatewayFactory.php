<?php

namespace App\Services\Sms;

class SmsGatewayFactory
{
    public function make(): SmsGateway
    {
        return match (config('reminders.sms.driver')) {
            'api' => new ApiSmsGateway,
            'unisms' => new UniSmsGateway,
            default => new LogSmsGateway,
        };
    }
}
