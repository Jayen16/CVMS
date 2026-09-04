@php
    $recoveryUnavailableOffline = config('system.instance_type') === 'facility' && config('offline.enabled');
@endphp

<x-layouts::auth :title="__('Reset password')">
    <div class="flex flex-col gap-6" x-data="{ otpSent: @js(session('otp_sent', false)), editing: @js(session('otp_locked', false)), locked: @js(session('otp_locked', false)), availableAt: @js(session('otp_available_at')), remaining: 0, timer: null, start() { this.tick(); this.timer = setInterval(() => this.tick(), 1000); }, tick() { this.remaining = Math.max(0, Math.ceil((new Date(this.availableAt) - new Date()) / 1000)); if (!this.remaining && this.timer) clearInterval(this.timer); } }" x-init="if (otpSent) start()">
        <x-auth-header :title="__('Forgot password')" :description="__('Enter your registered email address or contact number to receive a password reset code.')" />
        <x-auth-session-status class="text-center" :status="session('status')" />
        @if ($recoveryUnavailableOffline)
            <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                {{ __('Password recovery is unavailable while this facility is offline. Please contact the RHU administrator.') }}
            </div>
        @else
        @if (session('toast_error'))
            <div class="text-red-600 dark:text-red-400">{{ session('toast_error') }}</div>
        @endif

        <form method="POST" action="{{ route('password.otp.send') }}" class="flex flex-col gap-6">
            @csrf
            <flux:input name="identifier" :label="__('Email address or contact number')" :value="session('otp_identifier', old('identifier'))" type="text" required autofocus placeholder="email@example.com or 09171234567" x-bind:disabled="otpSent && !editing" />
            <input x-show="otpSent && !editing" x-bind:disabled="!otpSent || editing" type="hidden" name="identifier" value="{{ session('otp_identifier') }}">
            <button x-show="otpSent && !editing" type="button" class="-mt-4 text-left text-sm text-teal-700 hover:underline" @click="otpSent = false; editing = false; locked = false; availableAt = null; remaining = 0; if (timer) clearInterval(timer)">{{ __('Change email or phone number') }}</button>
            <p x-show="locked" class="-mt-3 text-sm font-medium text-red-600">{{ __('Verification is locked. Please contact or visit the RHU.') }}</p>
            <input type="hidden" name="mode" value="reset">
            <flux:button x-show="!otpSent || editing" variant="primary" type="submit" class="w-full">{{ __('Submit') }}</flux:button>
        </form>

        <form x-show="otpSent && !locked" x-cloak method="POST" action="{{ route('password.otp.verify') }}" class="flex flex-col gap-6">
            @csrf
            <input type="hidden" name="identifier" value="{{ session('otp_identifier') }}"><input type="hidden" name="mode" value="reset">
            <flux:input name="code" :label="__('Verification code')" inputmode="numeric" maxlength="6" required />
            <flux:input name="password" :label="__('New password')" type="password" required />
            <flux:input name="password_confirmation" :label="__('Confirm password')" type="password" required />
            <flux:button variant="primary" type="submit" class="w-full">{{ __('Reset password') }}</flux:button>
            <p class="text-center text-sm text-zinc-500">
                <span x-show="remaining > 0">{{ __('Resend OTP available in') }} <span x-text="Math.floor(remaining / 60) + ':' + String(remaining % 60).padStart(2, '0')"></span></span>
                <button x-show="remaining === 0" type="submit" form="resend-otp" class="text-teal-700 hover:underline">{{ __('Resend OTP') }}</button>
            </p>
        </form>
        <form id="resend-otp" method="POST" action="{{ route('password.otp.send') }}" class="hidden">@csrf<input type="hidden" name="identifier" value="{{ session('otp_identifier') }}"></form>
        <div class="text-center text-sm"><a class="text-teal-700 hover:underline" href="{{ route('account.activation') }}">{{ __('Activate my account') }}</a></div>
        @endif
        <div class="text-center text-sm"><a class="text-teal-700 hover:underline" href="{{ route('login') }}">{{ __('Return to log in') }}</a></div>
    </div>
</x-layouts::auth>
