<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

class LogSmsGateway implements SmsGateway
{
    public function send(string $recipient, string $message): void
    {
        Log::info('SMS reminder logged.', [
            'recipient' => $recipient,
            'message' => $message,
        ]);
    }
}
