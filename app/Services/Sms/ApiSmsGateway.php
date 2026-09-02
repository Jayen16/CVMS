<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ApiSmsGateway implements SmsGateway
{
    public function send(string $recipient, string $message): void
    {
        $url = config('reminders.sms.api.url');
        abort_if(blank($url), 503, 'SMS API is not configured.');
        $response = Http::acceptJson()->timeout((int) config('reminders.sms.api.timeout', 15))
            ->withToken((string) config('reminders.sms.api.token'))
            ->post($url, ['to' => $recipient, 'message' => $message, 'from' => config('reminders.sms.api.from')]);
        if ($response->failed()) throw new RuntimeException('SMS API request failed.');
    }
}
