<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ __('Welcome') }} - {{ config('app.name') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="rhu-auth-shell min-h-screen text-slate-900 dark:text-zinc-100">
    <div class="relative isolate overflow-hidden">
        <div class="rhu-auth-orb rhu-auth-orb-one"></div>
        <div class="rhu-auth-orb rhu-auth-orb-two"></div>

        <div class="mx-auto flex min-h-screen max-w-7xl flex-col px-4 py-6 sm:px-6 lg:px-8">
            <header
                class="flex items-center justify-between rounded-full border border-white/70 bg-white/80 px-4 py-3 shadow-sm backdrop-blur dark:border-white/10 dark:bg-zinc-950/80 sm:px-6">
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <x-app-logo-icon class="h-12 w-12 shadow-md" />
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">RHU</p>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ config('rhu.system_name') }}</p>
                    </div>
                </a>

            </header>

            <main class="flex flex-1 items-center py-10 lg:py-16">
                <div class="grid w-full items-center gap-8 lg:grid-cols-[1.1fr_0.9fr]">
                    <section class="space-y-6">
                        <p class="text-sm font-semibold uppercase tracking-[0.28em] text-emerald-700">Indang, Cavite</p>
                        <div class="space-y-4">
                            <h1
                                class="max-w-3xl text-4xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-5xl lg:text-6xl">
                                A more welcoming child immunization portal for families and RHU staff.
                            </h1>
                            <p class="max-w-2xl text-lg leading-8 text-slate-600 dark:text-zinc-300">
                                Manage child records, monitor due vaccinations, review submitted entries, and keep
                                clinic communication organized in one place.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3 h-10">

                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="app-panel border-white/80 bg-white/85 backdrop-blur dark:border-white/10 dark:bg-zinc-900/80">
                                <p class="eyebrow">Track</p>
                                <h2 class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">Child records</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-zinc-300">Keep profiles, vaccine history, and
                                    clinic follow-up easier to review.</p>
                            </div>
                            <div class="app-panel border-white/80 bg-white/85 backdrop-blur dark:border-white/10 dark:bg-zinc-900/80">
                                <p class="eyebrow">Coordinate</p>
                                <h2 class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">Barangay work</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-zinc-300">Support nurses, admins, and parents
                                    with one shared workflow.</p>
                            </div>
                            <div class="app-panel border-white/80 bg-white/85 backdrop-blur dark:border-white/10 dark:bg-zinc-900/80">
                                <p class="eyebrow">Inform</p>
                                <h2 class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">Clinic updates</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-zinc-300">Post announcements and help families
                                    stay informed about schedules.</p>
                            </div>
                        </div>
                    </section>

                    <section
                        class="welcome-login-shell">
                        <div class="flex items-center gap-4">
                            <x-app-logo-icon class="h-20 w-20 shadow-lg" />
                            <div>
                                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-emerald-700">Official
                                    Facility</p>
                                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ config('rhu.name') }}</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-zinc-300">{{ config('rhu.system_name') }}</p>
                            </div>
                        </div>



                        <div class="mt-6 flex flex-col gap-6 welcome-login-card">

                            <!-- Session Status -->
                            <x-auth-session-status class="text-center" :status="session('status')" />

                            {{-- <x-passkey-verify /> --}}

                            <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
                                @csrf

                                <!-- Login -->
                                <flux:input name="email" :label="__('Email address or phone number')" :value="old('email')" type="text"
                                    required autofocus autocomplete="username" placeholder="email@example.com or 09171234567" />

                                <!-- Password -->
                                <div class="relative">
                                    <flux:input name="password" :label="__('Password')" type="password" required
                                        autocomplete="current-password" :placeholder="__('Password')" viewable />

                                    @if (Route::has('password.request'))
                                    <flux:link class="absolute top-0 text-sm end-0 text-slate-700 dark:text-zinc-300" :href="route('password.request')"
                                        wire:navigate>
                                        {{ __('Forgot your password?') }}
                                    </flux:link>
                                    @endif
                                </div>

                                <!-- Remember Me -->
                                <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

                                <div class="flex items-center justify-end">
                                    <flux:button variant="primary" type="submit" class="w-full"
                                        data-test="login-button">
                                        {{ __('Log in') }}
                                    </flux:button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

</body>

</html>
