<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PasswordOtpNotification;
use App\Services\AccountRecoveryService;
use App\Services\Sms\SmsGatewayFactory;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PhonePasswordResetController extends Controller
{
    public function activation(): View
    {
        return view('pages.auth.activate-account');
    }

    public function showCreatePassword(string $token): View
    {
        return view('pages.auth.reset-password', ['isSetup' => true, 'token' => $token]);
    }

    public function sendOtp(Request $request, SmsGatewayFactory $sms): RedirectResponse
    {
        $identifier = trim($request->validate(['identifier' => ['required', 'string', 'max:255']])['identifier']);
        $mode = $request->validate(['mode' => ['nullable', 'in:reset,activation']])['mode'] ?? 'reset';
        $email = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? strtolower($identifier) : null;
        $lookup = $email ?? User::normalizePhone($identifier);
        $throttleKey = 'password-otp-send:'.hash('sha256', $lookup.'|'.$request->ip());
        $cooldownKey = 'password-otp-cooldown:'.hash('sha256', $lookup.'|'.$request->ip());
        $lockKey = 'password-otp-locked:'.hash('sha256', $lookup);

        $returnRoute = $mode === 'activation' ? 'account.activation' : 'password.request';

        if (app(RateLimiter::class)->tooManyAttempts($throttleKey, 5)) {
            return to_route($returnRoute)->with([
                'otp_sent' => (bool) session('otp_sent'),
                'otp_identifier' => $identifier,
                'toast_error' => 'Too many code requests. Please wait and try again later.',
            ]);
        }

        if (Cache::has($cooldownKey)) {
            return to_route($returnRoute)->with([
                'otp_sent' => (bool) session('otp_sent'),
                'otp_identifier' => $identifier,
                'otp_available_at' => session('otp_available_at'),
                'toast_error' => 'Please wait 5 minutes before requesting another code.',
            ]);
        }
        if (Cache::has($lockKey)) {
            return to_route($returnRoute)->with([
                'otp_locked' => true,
                'toast_error' => 'Verification is locked. Please contact or visit the RHU.',
                'otp_identifier' => $identifier,
            ]);
        }
        app(RateLimiter::class)->hit($throttleKey, 600);

        $user = User::query()->notArchived()
            ->when($mode === 'activation', fn ($query) => $query->whereNull('invitation_accepted_at'))
            ->when($mode === 'reset', fn ($query) => $query->whereNotNull('invitation_accepted_at')->where('is_active', true))
            ->where(function ($query) use ($email, $lookup): void {
                $query->where('email', $email ?? '')
                    ->orWhere('phone', $email === null ? $lookup : '');
            })->first();

        if ($user === null) {
            return to_route($mode === 'activation' ? 'account.activation' : 'password.request')->with([
                'otp_sent' => false,
                'toast_error' => $mode === 'activation'
                    ? 'No pending account was found for that email or contact number.'
                    : 'No account was found for that email or contact number.',
            ]);
        }

        $code = (string) random_int(100000, 999999);
        $key = $this->otpKey($lookup);
        $activation = $user->invitation_accepted_at === null;
        Cache::put($key, ['user_id' => $user->id, 'code' => Hash::make($code), 'attempts' => 0, 'activation' => $activation, 'mode' => $mode], now()->addMinutes(10));

        try {
            if ($email !== null) {
                $user->notify(new PasswordOtpNotification($code, $activation));
            } else {
                $sms->make()->send(User::smsRecipient($lookup), ($activation ? 'Your CVMS account activation' : 'Your CVMS password reset')." code is {$code}. It expires in 10 minutes.");
            }
            Cache::put($cooldownKey, true, now()->addMinutes(5));
        } catch (\Throwable $exception) {
            Cache::forget($key);
            report($exception);

            return to_route($returnRoute)->with('toast_error', 'The verification code could not be sent. Please try again later.');
        }

        return to_route($mode === 'activation' ? 'account.activation' : 'password.request')->with([
            'status' => 'If that account exists, a verification code was sent.',
            'otp_sent' => true,
            'otp_identifier' => $identifier,
            'otp_available_at' => now()->addMinutes(5)->toIso8601String(),
            'otp_mode' => $mode,
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'mode' => ['nullable', 'in:reset,activation'],
        ]);
        $mode = $validated['mode'] ?? 'reset';
        $identifier = trim($validated['identifier']);
        $lookup = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? strtolower($identifier) : User::normalizePhone($identifier);
        $key = $this->otpKey($lookup);
        $challenge = Cache::get($key);
        abort_unless($challenge, 422, 'The verification code is invalid or expired.');
        abort_unless(($challenge['mode'] ?? 'reset') === $mode, 422, 'The verification code is invalid or expired.');
        abort_if($mode === 'activation' && ($challenge['activation'] ?? false) !== true, 422, 'The verification code is invalid or expired.');
        abort_if(($challenge['attempts'] ?? 0) >= 3, 422, 'Verification is locked. Please contact or visit the RHU.');

        if (! Hash::check($validated['code'], $challenge['code'])) {
            $challenge['attempts'] = ($challenge['attempts'] ?? 0) + 1;
            if ($challenge['attempts'] >= 3) {
                Cache::put('password-otp-locked:'.hash('sha256', $lookup), true, now()->addDay());
                Cache::forget($key);

                return to_route($mode === 'activation' ? 'account.activation' : 'password.request')->with([
                    'otp_locked' => true,
                    'toast_error' => 'Verification is locked after 3 incorrect attempts. Please contact or visit the RHU.',
                ]);
            }
            Cache::put($key, $challenge, now()->addMinutes(10));
            abort(422, 'The verification code is invalid or expired.');
        }

        $user = User::query()->notArchived()->whereKey($challenge['user_id'])->firstOrFail();
        $user->forceFill(['password' => $validated['password'], 'invitation_accepted_at' => $user->invitation_accepted_at ?? now(), 'is_active' => true])->save();
        Cache::forget($key);

        return to_route('login')->with('status', $mode === 'activation' ? 'Account activated successfully. You can now log in.' : 'Password reset successfully. You can now log in.');
    }

    private function otpKey(string $identifier): string
    {
        return 'password-otp:'.hash('sha256', $identifier);
    }

    public function showLink(string $token): View
    {
        abort_unless(Cache::has('password-reset-link:'.hash('sha256', $token)), 410, 'This password link is invalid or expired.');

        return view('pages.auth.reset-password-phone', compact('token'));
    }

    public function resetLink(Request $request, string $token): RedirectResponse
    {
        $validated = $request->validate(['password' => ['required', 'confirmed', Password::defaults()]]);
        $key = 'password-reset-link:'.hash('sha256', $token);
        $challenge = Cache::pull($key);
        abort_unless($challenge, 410, 'This password link is invalid or expired.');
        User::query()->whereKey($challenge['user_id'])->firstOrFail()->forceFill(['password' => $validated['password']])->save();

        return to_route('login')->with('status', 'Password updated successfully.');
    }

    public function sendStaffLink(Request $request, User $user, AccountRecoveryService $recovery): RedirectResponse
    {
        abort_unless(auth()->user()->canManageBarangayStaff(), 403);
        abort_unless($user->isNurse() || $user->isBarangayAdmin(), 404);
        $channel = $request->validate(['channel' => ['required', 'in:email,sms']])['channel'];
        $recovery->send($user, $channel);

        return to_route('nurses.index')->with('status', 'Password reset link sent by '.strtoupper($channel).'.');
    }

    public function create(): RedirectResponse
    {
        return to_route('password.request');
    }

    public function sendCode(Request $request, SmsGatewayFactory $sms): RedirectResponse
    {
        $validated = $request->validate(['phone' => ['required', 'string', 'max:32']]);
        $phone = User::normalizePhone($validated['phone']);
        $key = 'password-reset-phone:'.sha1((string) $phone.'|'.$request->ip());

        abort_if(app(RateLimiter::class)->tooManyAttempts($key, 5), 429, 'Too many reset requests. Try again later.');
        app(RateLimiter::class)->hit($key, 600);

        $user = User::query()->where('phone', $phone)->where(function ($query): void {
            $query->where('role', 'parent')->orWhereJsonContains('roles', 'parent');
        })->notArchived()->first();
        if ($user) {
            $code = (string) random_int(100000, 999999);
            $cacheKey = 'password-reset-otp:'.sha1((string) $phone);
            $purpose = $user->invitation_accepted_at === null ? 'activation' : 'reset';

            Cache::put($cacheKey, [
                'user_id' => $user->id,
                'code' => Hash::make($code),
                'purpose' => $purpose,
                'attempts' => 0,
            ], now()->addMinutes(10));

            $message = $purpose === 'activation'
                ? "Your CVMS account activation code is {$code}. It expires in 10 minutes."
                : "Your CVMS password reset code is {$code}. It expires in 10 minutes.";

            try {
                $sms->make()->send(User::smsRecipient($phone), $message);
            } catch (\Throwable $exception) {
                Cache::forget($cacheKey);
                report($exception);

                return to_route('password.phone.request')->with('toast_error', 'The verification code could not be sent. Please try again later.');
            }
        }

        return to_route('password.phone.request')->with('status', 'If that phone number belongs to an account, a verification code was sent.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $phone = User::normalizePhone($validated['phone']);
        $cacheKey = 'password-reset-otp:'.sha1((string) $phone);
        $challenge = Cache::get($cacheKey);
        abort_unless($challenge, 422, 'The verification code is invalid or expired.');

        if (($challenge['attempts'] ?? 0) >= 5) {
            Cache::forget($cacheKey);
            abort(422, 'Too many incorrect verification attempts. Request a new code.');
        }

        if (! Hash::check($validated['code'], $challenge['code'])) {
            $challenge['attempts'] = ($challenge['attempts'] ?? 0) + 1;
            Cache::put($cacheKey, $challenge, now()->addMinutes(10));
            abort(422, 'The verification code is invalid or expired.');
        }

        $user = User::query()->whereKey($challenge['user_id'])->where('phone', $phone)->where(function ($query): void {
            $query->where('role', 'parent')->orWhereJsonContains('roles', 'parent');
        })->notArchived()->firstOrFail();
        $user->forceFill([
            'password' => $validated['password'],
            'invitation_accepted_at' => $user->invitation_accepted_at ?? now(),
            'is_active' => true,
        ])->save();
        Cache::forget($cacheKey);

        $message = ($challenge['purpose'] ?? 'reset') === 'activation'
            ? 'Account activated successfully. You can now log in with your phone number.'
            : 'Password reset successfully. You can now log in with your phone number.';

        return to_route('login')->with('status', $message);
    }
}
