<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email address or phone number and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Login -->
            <flux:input
                name="email"
                :label="__('Email address or phone number')"
                :value="old('email')"
                type="text"
                required
                autofocus
                autocomplete="username"
                placeholder="email@example.com or 09171234567"
            />

            <!-- Password -->
            <div class="flex flex-col gap-2">
                <flux:input
                    id="login-password"
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                />

                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 text-sm">
                    <label class="inline-flex items-center gap-2 text-zinc-600 dark:text-zinc-300" for="show-login-password">
                        <input id="show-login-password" type="checkbox" class="h-4 w-4 rounded border-zinc-300 text-accent focus:ring-accent">
                        <span>{{ __('Show password') }}</span>
                    </label>

                    @if (Route::has('password.request') && !(config('system.instance_type') === 'facility' && config('offline.enabled')))
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <flux:link :href="route('password.request')" wire:navigate>
                                {{ __('Forgot your password?') }}
                            </flux:link>
                            <span class="text-zinc-400">|</span>
                            <flux:link :href="route('account.activation')" wire:navigate>
                                {{ __('Activate my account') }}
                            </flux:link>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Don\'t have an account?') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
        </div>
    </div>

    <script>
        document.getElementById('show-login-password')?.addEventListener('change', function () {
            const password = document.getElementById('login-password');

            if (password) {
                password.type = this.checked ? 'text' : 'password';
            }
        });
    </script>
</x-layouts::auth>
