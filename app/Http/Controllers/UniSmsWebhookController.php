<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UniSmsWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $configuredSecret = (string) config('reminders.sms.unisms.webhook_secret_key');
        $receivedSecret = (string) $request->header('webhook-secret-key');

        abort_if(
            $configuredSecret === ''
                || $receivedSecret === ''
                || ! hash_equals($configuredSecret, $receivedSecret),
            401,
            'Invalid webhook secret.'
        );

        $payload = $request->validate([
            'event' => ['required', 'string'],
            'id' => ['nullable', 'string'],
            'message' => ['nullable', 'array'],
            'message.status' => ['nullable', 'string'],
            'message.reference_id' => ['nullable', 'string'],
            'message.recipient' => ['nullable', 'string'],
            'message.fail_reason' => ['nullable', 'string'],
        ]);

        Log::info('UniSMS message status received.', [
            'event' => $payload['event'],
            'message_id' => $payload['id'] ?? data_get($payload, 'message.reference_id'),
            'status' => data_get($payload, 'message.status'),
            'recipient' => data_get($payload, 'message.recipient'),
            'fail_reason' => data_get($payload, 'message.fail_reason'),
        ]);

        return response()->json(['received' => true]);
    }
}
