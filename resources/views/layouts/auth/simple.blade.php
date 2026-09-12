<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['includeAppearance' => false])
    </head>
    <body class="rhu-auth-shell antialiased">
        <div class="relative flex min-h-svh items-center justify-center overflow-hidden px-4 py-8 sm:px-6 lg:px-8">
            <div class="rhu-auth-orb rhu-auth-orb-one"></div>
            <div class="rhu-auth-orb rhu-auth-orb-two"></div>

            <div class="relative flex w-full max-w-2xl flex-col items-center gap-6">
                <a href="{{ route('home') }}" class="flex items-center gap-4" wire:navigate>
                    <x-app-logo-icon class="h-16 w-16 shadow-lg" />
                    <div>
                        <p class="rhu-auth-kicker">{{ config('app.name', 'Laravel') }}</p>
                        <h1 class="rhu-auth-title">{{ config('rhu.name') }}</h1>
                        <p class="rhu-auth-subtitle">{{ config('rhu.system_name') }}</p>
                    </div>
                    <span class="sr-only">{{ config('app.name') }}</span>
                </a>

                <div class="w-full rounded-[2rem] border border-white/70 bg-white/92 p-6 shadow-[0_20px_60px_rgba(15,23,42,0.12)] backdrop-blur md:p-8 dark:border-white/10 dark:bg-slate-950/88">
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
