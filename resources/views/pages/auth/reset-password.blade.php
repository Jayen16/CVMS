@php($isSetup = $isSetup ?? false)

<x-layouts::auth :title="$isSetup ? __('Create password') : __('Reset password')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="$isSetup ? __('Create password') : __('Reset password')"
            :description="$isSetup ? __('Create a password to finish setting up your account.') : __('Please enter your new password below')"
        />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Token -->
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <!-- Email Address -->
            <flux:input
                name="email"
                value="{{ request('email') }}"
                :label="__('Email')"
                type="email"
                required
                readonly
                aria-readonly="true"
                autocomplete="email"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="reset-password-button">
                    {{ $isSetup ? __('Create password') : __('Reset password') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::auth>
