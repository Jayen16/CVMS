@php($isReset = ($codeMode ?? 'activation') === 'reset')
<x-layouts::auth :title="$isReset ? __('Enter password reset code') : __('Enter activation code')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="$isReset ? __('Enter password reset code') : __('Enter activation code')" :description="__('Enter your existing code and choose a password.')" />
        @if ($errors->any())
            <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form method="POST" action="{{ route($isReset ? 'password.otp.verify' : 'account.activation.otp.verify') }}" class="flex flex-col gap-6">
            @csrf
            <input type="hidden" name="mode" value="{{ $isReset ? 'reset' : 'activation' }}">
            <flux:input name="identifier" :label="__('Registered email or contact number')" :value="old('identifier', request('identifier', ''))" type="text" required autofocus />
            <flux:input name="code" :label="$isReset ? __('Password reset code') : __('Activation code')" inputmode="numeric" maxlength="10" autocomplete="one-time-code" required />
            <p class="text-xs text-zinc-500">{{ __('Your password must be at least 12 characters and include uppercase and lowercase letters, a number, and a symbol.') }}</p>
            <flux:input name="password" :label="__('Password')" type="password" autocomplete="new-password" required />
            <flux:input name="password_confirmation" :label="__('Confirm password')" type="password" autocomplete="new-password" required />
            <flux:button variant="primary" type="submit" class="w-full">{{ $isReset ? __('Reset password') : __('Activate my account') }}</flux:button>
        </form>
        <a class="text-center text-sm text-teal-700 hover:underline" href="{{ route($isReset ? 'password.request' : 'account.activation') }}">{{ __('Request a new code') }}</a>
    </div>
</x-layouts::auth>
