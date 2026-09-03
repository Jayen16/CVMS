<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class UniSmsGateway implements SmsGateway
{
    public function send(string $recipient, string $message): void
    {
        $baseUrl = trim((string) config('reminders.sms.unisms.base_url'));
        $secretKey = trim((string) config('reminders.sms.unisms.secret_key'));
        $senderId = trim((string) config('reminders.sms.unisms.sender_id'));

        if ($baseUrl === '' || $secretKey === '' || $senderId === '') {
            throw new RuntimeException('UniSMS is not configured. Set the base URL, secret key, and sender ID.');
        }

        $response = Http::acceptJson()
            ->asJson()
            ->timeout((int) config('reminders.sms.unisms.timeout', 15))
            ->withBasicAuth($secretKey, '')
            ->post(rtrim($baseUrl, '/').'/sms', [
                'recipient' => $recipient,
                'content' => $message,
                'sender_id' => $senderId,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('UniSMS request failed: '.$response->status());
        }
    }
}
