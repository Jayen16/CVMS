<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['includeAppearance' => false])
    </head>
    <body class="rhu-auth-shell antialiased">
        <div class="relative flex min-h-svh items-center justify-center overflow-hidden px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
            <div class="rhu-auth-orb rhu-auth-orb-one"></div>
            <div class="rhu-auth-orb rhu-auth-orb-two"></div>

            <div class="relative flex w-full max-w-2xl flex-col items-center gap-5 sm:gap-6">
                <a href="{{ route('home') }}" class="flex w-full max-w-xl items-center justify-center gap-3 text-center sm:gap-4 sm:text-left" wire:navigate>
                    <x-app-logo-icon class="h-12 w-12 shrink-0 shadow-lg sm:h-14 sm:w-14 lg:h-16 lg:w-16" />
                    <div class="min-w-0">
                        <p class="rhu-auth-kicker">{{ config('app.name', 'Laravel') }}</p>
                        <h1 class="rhu-auth-title">{{ config('rhu.name') }}</h1>
                        <p class="rhu-auth-subtitle">{{ config('rhu.system_name') }}</p>
                    </div>
                    <span class="sr-only">{{ config('app.name') }}</span>
                </a>

                <div class="rhu-auth-card w-full rounded-3xl border border-white/70 bg-white/92 p-5 shadow-[0_20px_60px_rgba(15,23,42,0.12)] backdrop-blur sm:rounded-[2rem] sm:p-7 md:p-8 dark:border-white/10 dark:bg-slate-950/88">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
