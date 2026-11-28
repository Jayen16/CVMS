<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AccountActivationNotification;
use App\Services\Sms\SmsGatewayFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AccountRecoveryService
{
    public function sendActivation(User $user, string $channel): string
    {
        if ($channel === 'email') {
            abort_if(blank($user->email), 422, 'This account has no registered email address.');
            $user->notify(new AccountActivationNotification);

            return 'email';
        }

        $this->sendSmsOtp($user, true);

        return 'sms';
    }

    public function send(User $user, string $channel): string
    {
        if ($channel === 'email') {
            abort_if(blank($user->email), 422, 'This account has no registered email address.');
            $status = Password::sendResetLink(['email' => $user->email]);
            abort_unless($status === Password::RESET_LINK_SENT, 422, __($status));
            return 'email';
        }

        $this->sendSmsOtp($user, $user->invitation_accepted_at === null);
        return 'sms';
    }

    private function sendSmsOtp(User $user, bool $activation): void
    {
        $phone = User::normalizePhone($user->phone);
        abort_if(blank($phone), 422, 'This account has no registered phone number.');
        $recipient = User::smsRecipient($phone);
        abort_unless(preg_match('/\A\+639[0-9]{9}\z/', $recipient), 422, 'This account needs a valid Philippine mobile number.');
        abort_if(Cache::has('password-otp-locked:'.hash('sha256', $phone)), 422, 'Verification is locked. Please contact or visit the RHU.');

        $key = 'password-otp:'.hash('sha256', $phone);
        $code = (string) random_int(1000000000, 9999999999);
        Cache::put($key, [
            'user_id' => $user->id,
            'code' => Hash::make($code),
            'attempts' => 0,
            'activation' => $activation,
            'mode' => $activation ? 'activation' : 'reset',
        ], now()->addMinutes(10));

        try {
            app(SmsGatewayFactory::class)->make()->send($recipient, $code);
        } catch (\Throwable $exception) {
            Cache::forget($key);
            throw $exception;
        }
    }
}
