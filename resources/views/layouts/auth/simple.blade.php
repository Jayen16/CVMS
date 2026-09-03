<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="rhu-auth-shell antialiased">
        <div class="relative flex min-h-svh items-center justify-center overflow-hidden px-4 py-8 sm:px-6 lg:px-8">
            <div class="rhu-auth-orb rhu-auth-orb-one"></div>
            <div class="rhu-auth-orb rhu-auth-orb-two"></div>

            <div class="relative grid w-full max-w-6xl gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                <section class="rhu-auth-showcase hidden lg:flex">
                    <div class="rhu-auth-showcase-inner">
                        <div class="flex items-center gap-4">
                            <x-app-logo-icon class="h-18 w-18 shadow-lg" />
                            <div>
                                <p class="rhu-auth-kicker">Municipality of Indang</p>
                                <h1 class="rhu-auth-title">{{ config('rhu.name') }}</h1>
                                <p class="rhu-auth-subtitle">{{ config('rhu.system_name') }}</p>
                            </div>
                        </div>

                        <p class="rhu-auth-copy">
                            A cleaner and more welcoming workspace for managing child immunization records, clinic updates, and coordinated follow-up across barangays.
                        </p>
                    </div>
                </section>

                <div class="flex flex-col justify-center">
                    <a href="{{ route('home') }}" class="mx-auto mb-5 flex w-full max-w-md items-center gap-3 lg:hidden" wire:navigate>
                        <x-app-logo-icon class="h-14 w-14 shadow-md" />
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-emerald-700">RHU</p>
                            <p class="text-base font-semibold text-slate-900">{{ config('rhu.system_name') }}</p>
                        </div>
                        <span class="sr-only">{{ config('app.name') }}</span>
                    </a>

                    <div class="mx-auto flex w-full max-w-md flex-col gap-4">
                        <div class="rounded-[2rem] border border-white/70 bg-white/92 p-6 shadow-[0_20px_60px_rgba(15,23,42,0.12)] backdrop-blur md:p-8 dark:border-white/10 dark:bg-slate-950/88">
                            {{ $slot }}
                        </div>
                    </div>
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
