<?php

namespace App\Services;

use App\Models\User;
use App\Services\Sms\SmsGatewayFactory;
use Illuminate\Support\Facades\Password;

class AccountRecoveryService
{
    public function send(User $user, string $channel): string
    {
        if ($channel === 'email') {
            abort_if(blank($user->email), 422, 'This account has no registered email address.');
            $status = Password::sendResetLink(['email' => $user->email]);
            abort_unless($status === Password::RESET_LINK_SENT, 422, __($status));
            return 'email';
        }

        abort_if(blank($user->phone), 422, 'This account has no registered phone number.');
        app(SmsGatewayFactory::class)->make()->send(
            User::smsRecipient($user->phone),
            'CVMS: Go to Activate My Account and enter your registered phone number to create your account.'
        );
        return 'sms';
    }
}
