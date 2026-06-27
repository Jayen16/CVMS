<?php

namespace App\Services\Sms;

class SmsGatewayFactory
{
    public function make(): SmsGateway
    {
        return match (config('reminders.sms.driver')) {
            'sim800c' => new Sim800cSmsGateway,
            default => new LogSmsGateway,
        };
    }
}
