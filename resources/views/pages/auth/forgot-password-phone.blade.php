<x-layouts::auth :title="__('Activate or reset with phone')">
    <x-auth-header :title="__('Activate or reset your account')" :description="__('Enter your registered phone number to receive a one-time verification code.')" />
    @if (session('toast_error'))
        <div x-data x-init="window.Flux?.toast({ variant: 'danger', text: @js(session('toast_error')) })"></div>
    @endif
    @if (session('status'))<div class="app-alert-success mb-5">{{ session('status') }}</div>@endif
    <form method="POST" action="{{ route('password.phone.send') }}" class="flex flex-col gap-6">
        @csrf
        <flux:input name="phone" :label="__('Registered phone number')" type="tel" value="{{ old('phone') }}" required autocomplete="tel" />
        <flux:button variant="primary" type="submit" class="w-full">{{ __('Send verification code') }}</flux:button>
    </form>
    <div class="mt-6 border-t pt-6 dark:border-zinc-700">
            <p class="mb-3 text-sm text-zinc-500">Already received a verification code?</p>
        <form method="POST" action="{{ route('password.phone.reset') }}" class="flex flex-col gap-4">
            @csrf
            <flux:input name="phone" :label="__('Phone number')" type="tel" required />
            <flux:input name="code" :label="__('Verification code')" inputmode="numeric" maxlength="6" required />
            <flux:input name="password" :label="__('New password')" type="password" required />
            <flux:input name="password_confirmation" :label="__('Confirm password')" type="password" required />
            <flux:button type="submit" variant="primary" class="w-full">{{ __('Activate / reset password') }}</flux:button>
        </form>
    </div>
    <div class="mt-5 text-center text-sm"><a class="text-teal-700 hover:underline" href="{{ route('password.request') }}">Use email instead</a></div>
</x-layouts::auth>
