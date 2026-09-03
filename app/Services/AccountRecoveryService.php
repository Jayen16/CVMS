<?php

namespace App\Services;

use App\Models\User;
use App\Services\Sms\SmsGatewayFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

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
        $token = Str::random(64);
        Cache::put('password-reset-link:'.hash('sha256', $token), ['user_id' => $user->id], now()->addHour());
        $url = route('password.phone.link', ['token' => $token]);
        app(SmsGatewayFactory::class)->make()->send($user->phone, "CVMS password reset link: {$url} This link expires in 1 hour.");
        return 'sms';
    }
}
