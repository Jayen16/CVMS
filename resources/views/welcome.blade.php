<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ __('Welcome') }} - {{ config('app.name') }}</title>

    <link rel="icon" href="{{ asset('images/child_vacc_icon.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('images/child_vacc_icon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="rhu-auth-shell min-h-svh text-slate-900 dark:text-zinc-100">
    <div class="relative isolate min-h-svh overflow-hidden">
        <div class="rhu-auth-orb rhu-auth-orb-one"></div>
        <div class="rhu-auth-orb rhu-auth-orb-two"></div>

        <div class="mx-auto flex min-h-svh max-w-7xl flex-col px-3 py-3 sm:px-6 sm:py-6 lg:px-8">
            <header
                class="flex items-center justify-between rounded-2xl border border-white/80 bg-white/75 px-3 py-2 shadow-sm backdrop-blur dark:border-white/10 dark:bg-zinc-950/75 sm:px-6 sm:py-3">
                <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-3">
                    <x-app-logo-icon class="h-10 w-10 sm:h-12 sm:w-12" />
                    <div class="min-w-0">
                        <p class="text-[0.65rem] font-bold uppercase tracking-[0.24em] text-emerald-700">RHU</p>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ config('rhu.system_name') }}</p>
                    </div>
                </a>
                <div class="hidden items-center gap-2 text-xs font-medium text-slate-500 sm:flex dark:text-zinc-400">
                    <span class="h-2 w-2 rounded-full bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,0.12)]"></span>
                    Secure clinic access
                </div>
            </header>

            <main class="flex flex-1 items-start pt-6 pb-2 sm:pt-8 sm:pb-4 lg:items-center lg:py-16">
                <div class="grid w-full min-w-0 items-start gap-7 sm:gap-10 lg:items-center lg:grid-cols-[1.05fr_0.95fr] lg:gap-16">
                    <section class="hidden space-y-7 lg:order-first lg:block lg:pb-6">
                        <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50/80 px-3 py-1.5 text-xs font-semibold text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-300">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-[0.65rem] text-white">+</span>
                            Care that keeps families on schedule
                        </div>
                        <div class="space-y-4">
                            <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                            <h1
                                class="hidden max-w-3xl flex-1 text-4xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-5xl lg:block lg:text-6xl">
                                Every child’s next healthy step, <span class="text-emerald-700">within reach.</span>
                            </h1>
                                <img
                                    class="h-32 w-32 shrink-0 self-center rounded-full object-contain shadow-lg ring-4 ring-white/80 sm:h-40 sm:w-40 sm:self-auto lg:h-60 lg:w-60"
                                    src="{{ asset('images/child_vacc_icon.png') }}"
                                    alt="Child Vaccination Management System logo"
                                >
                            </div>
                            <p class="max-w-2xl text-lg leading-8 text-slate-600 dark:text-zinc-300">
                                The secure vaccination workspace for Rural Health Unit staff and families in your community.
                                Find records, follow schedules, and keep care moving forward.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-x-6 gap-y-3 text-sm font-medium text-slate-600 dark:text-zinc-300">
                            <span class="flex items-center gap-2"><span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-xs text-emerald-700">✓</span> Centralized records</span>
                            <span class="flex items-center gap-2"><span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-xs text-emerald-700">✓</span> Timely reminders</span>
                        </div>

                        <div class="grid max-w-2xl gap-3 sm:grid-cols-3">
                            <div class="rhu-feature-tile">
                                <p class="rhu-feature-icon">01</p>
                                <h2>Track</h2>
                                <p>Child records and vaccine history in one view.</p>
                            </div>
                            <div class="rhu-feature-tile">
                                <p class="rhu-feature-icon">02</p>
                                <h2>Coordinate</h2>
                                <p>One shared workflow for every barangay.</p>
                            </div>
                            <div class="rhu-feature-tile">
                                <p class="rhu-feature-icon">03</p>
                                <h2>Inform</h2>
                                <p>Clear clinic updates for families.</p>
                            </div>
                        </div>
                    </section>

                    <div class="order-first flex justify-center lg:hidden">
                        <img
                            class="h-20 w-20 rounded-full object-contain shadow-lg ring-4 ring-white/80 sm:h-24 sm:w-24"
                            src="{{ asset('images/child_vacc_icon.png') }}"
                            alt="Child Vaccination Management System logo"
                        >
                    </div>

                    <section
                        class="welcome-login-shell order-last w-full min-w-0 max-w-2xl justify-self-center lg:order-last lg:max-w-none">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.24em] text-emerald-700">Welcome back</p>
                                <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white sm:mt-3 sm:text-3xl">Sign in to continue</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-zinc-300">Access your vaccination records and clinic workspace.</p>
                            </div>
                            <span class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-xl text-emerald-700 sm:flex dark:bg-emerald-950/60 dark:text-emerald-300">↗</span>
                        </div>



                        <div class="mt-3 flex flex-col gap-4 lg:mt-7 lg:gap-6">

                            <!-- Session Status -->
                            <x-auth-session-status class="text-center" :status="session('status')" />

                            {{-- <x-passkey-verify /> --}}

                            <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4 lg:gap-6">
                                @csrf

                                <!-- Login -->
                                <flux:input name="email" :label="__('Email address or phone number')" :value="old('email')" type="text"
                                    required autofocus autocomplete="username" placeholder="Email or phone number" />

                                <!-- Password -->
                                <div class="flex flex-col gap-2">
                                    <div class="relative">
                                        <flux:input id="login-password" name="password" :label="__('Password')" type="password" required
                                            autocomplete="current-password" :placeholder="__('Password')" />
                                        <button type="button" data-password-toggle aria-controls="login-password"
                                            aria-label="{{ __('Show password') }}"
                                            class="absolute end-2 top-8 rounded p-1 text-slate-500 hover:text-slate-700">
                                            <flux:icon.eye class="size-5" data-password-eye />
                                            <flux:icon.eye-slash class="hidden size-5" data-password-eye-slash />
                                        </button>
                                    </div>

                                    <div class="relative z-20 flex flex-wrap items-center justify-between gap-x-3 gap-y-2 text-sm pointer-events-auto">
                                        @if (Route::has('password.request') && !(config('system.instance_type') === 'facility' && config('offline.enabled')))
                                        <div class="relative z-20 flex flex-wrap items-center gap-x-2 gap-y-1 pointer-events-auto">
                                            <flux:link class="relative z-20 pointer-events-auto text-slate-700 dark:text-zinc-300" :href="route('password.request')" wire:navigate>
                                                {{ __('Forgot your password?') }}
                                            </flux:link>
                                            <span class="hidden text-slate-400 sm:inline">|</span>
                                            <flux:link class="relative z-20 pointer-events-auto text-slate-700 dark:text-zinc-300" :href="route('account.activation')" wire:navigate>
                                                {{ __('Activate my account') }}
                                            </flux:link>
                                        </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center justify-end">
                                    <flux:button variant="primary" type="submit" class="min-h-12 w-full !rounded-xl !bg-emerald-600 !py-3 !text-white hover:!bg-emerald-700"
                                        data-test="login-button">
                                        {{ __('Sign in securely') }}
                                    </flux:button>
                                </div>
                            </form>
                        </div>
                        <p class="mt-2 text-center text-xs leading-5 text-slate-500 dark:text-zinc-400 lg:mt-6">Your information is protected and only available to authorized users.</p>
                    </section>
                </div>
            </main>
        </div>
    </div>

    @fluxScripts
    <script>
        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const password = document.getElementById(button.getAttribute('aria-controls'));
                const visible = password.type === 'text';

                password.type = visible ? 'password' : 'text';
                button.setAttribute('aria-label', visible ? @json(__('Show password')) : @json(__('Hide password')));
                button.querySelector('[data-password-eye]').classList.toggle('hidden', !visible);
                button.querySelector('[data-password-eye-slash]').classList.toggle('hidden', visible);
            });
        });
    </script>
</body>

</html>
