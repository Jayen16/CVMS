<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Sms\SmsGatewayFactory;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use App\Services\AccountRecoveryService;

class PhonePasswordResetController extends Controller
{
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

    public function create(): View
    {
        return view('pages.auth.forgot-password-phone');
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
            Cache::put('password-reset-otp:'.sha1((string) $phone), ['user_id' => $user->id, 'code' => Hash::make($code)], now()->addMinutes(10));
            $sms->make()->send($phone, "Your CVMS password reset code is {$code}. It expires in 10 minutes.");
        }

        return to_route('password.phone.request')->with('status', 'If that phone number belongs to a parent account, a reset code was sent.');
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
        abort_unless($challenge && Hash::check($validated['code'], $challenge['code']), 422, 'The reset code is invalid or expired.');

        $user = User::query()->whereKey($challenge['user_id'])->where('phone', $phone)->where(function ($query): void {
            $query->where('role', 'parent')->orWhereJsonContains('roles', 'parent');
        })->notArchived()->firstOrFail();
        $user->forceFill(['password' => $validated['password']])->save();
        Cache::forget($cacheKey);

        return to_route('login')->with('status', 'Password reset successfully. You can now log in with your phone number.');
    }
}
