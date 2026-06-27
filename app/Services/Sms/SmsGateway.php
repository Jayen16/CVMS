<?php

namespace App\Services\Sms;

interface SmsGateway
{
    public function send(string $recipient, string $message): void;
}
