@props([
    'code',
    'title',
    'message',
    'eyebrow' => 'System notice',
    'primaryLabel' => 'Return home',
    'primaryUrl' => url('/'),
    'secondaryLabel' => null,
    'secondaryUrl' => null,
    'accent' => 'emerald',
])

@php
    $accentMap = [
        'emerald' => [
            'badge' => 'bg-emerald-500/15 text-emerald-700 ring-1 ring-emerald-500/20 dark:text-emerald-200 dark:ring-emerald-400/20',
            'code' => 'from-emerald-500 via-teal-500 to-sky-500',
            'glow' => 'bg-emerald-300/30',
            'panel' => 'border-emerald-200/70 bg-[linear-gradient(160deg,#ffffff_0%,#f0fdf4_35%,#ecfeff_100%)] dark:border-white/10 dark:bg-[linear-gradient(160deg,#0f172a_0%,#111827_40%,#0b1220_100%)]',
            'orb' => 'bg-emerald-200/45',
        ],
        'amber' => [
            'badge' => 'bg-amber-500/15 text-amber-700 ring-1 ring-amber-500/20 dark:text-amber-200 dark:ring-amber-400/20',
            'code' => 'from-amber-500 via-orange-500 to-rose-500',
            'glow' => 'bg-amber-300/30',
            'panel' => 'border-amber-200/70 bg-[linear-gradient(160deg,#fffdf5_0%,#fffbeb_35%,#fff7ed_100%)] dark:border-white/10 dark:bg-[linear-gradient(160deg,#1c1204_0%,#1f172a_40%,#1a120b_100%)]',
            'orb' => 'bg-amber-200/45',
        ],
        'red' => [
            'badge' => 'bg-red-500/15 text-red-700 ring-1 ring-red-500/20 dark:text-red-200 dark:ring-red-400/20',
            'code' => 'from-red-500 via-rose-500 to-orange-500',
            'glow' => 'bg-red-300/30',
            'panel' => 'border-red-200/70 bg-[linear-gradient(160deg,#fff7f7_0%,#fef2f2_35%,#fff7ed_100%)] dark:border-white/10 dark:bg-[linear-gradient(160deg,#1f1015_0%,#1a1325_40%,#1b1310_100%)]',
            'orb' => 'bg-red-200/45',
        ],
        'sky' => [
            'badge' => 'bg-sky-500/15 text-sky-700 ring-1 ring-sky-500/20 dark:text-sky-200 dark:ring-sky-400/20',
            'code' => 'from-sky-500 via-cyan-500 to-teal-500',
            'glow' => 'bg-sky-300/30',
            'panel' => 'border-sky-200/70 bg-[linear-gradient(160deg,#f8fdff_0%,#eff6ff_35%,#ecfeff_100%)] dark:border-white/10 dark:bg-[linear-gradient(160deg,#071320_0%,#0f172a_40%,#102033_100%)]',
            'orb' => 'bg-sky-200/45',
        ],
    ];

    $theme = $accentMap[$accent] ?? $accentMap['emerald'];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} {{ $title }} - {{ config('app.name') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="rhu-auth-shell min-h-screen text-slate-900 dark:text-zinc-100">
    <div class="relative isolate min-h-screen overflow-hidden">
        <div class="rhu-auth-orb rhu-auth-orb-one {{ $theme['orb'] }}"></div>
        <div class="rhu-auth-orb rhu-auth-orb-two {{ $theme['orb'] }}"></div>

        <div class="mx-auto flex min-h-screen max-w-7xl flex-col px-4 py-6 sm:px-6 lg:px-8">
            <header class="flex items-center justify-between rounded-full border border-white/70 bg-white/80 px-4 py-3 shadow-sm backdrop-blur dark:border-white/10 dark:bg-zinc-950/80 sm:px-6">
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <x-app-logo-icon class="h-12 w-12 shadow-md" />
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Indang RHU</p>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ config('rhu.system_name') }}</p>
                    </div>
                </a>

                <span class="hidden rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] sm:inline-flex {{ $theme['badge'] }}">
                    HTTP {{ $code }}
                </span>
            </header>

            <main class="flex flex-1 items-center py-10 lg:py-16">
                <div class="grid w-full items-center gap-8 lg:grid-cols-[1.1fr_0.9fr]">
                    <section class="space-y-6">
                        <p class="text-sm font-semibold uppercase tracking-[0.28em] text-emerald-700 dark:text-emerald-300">{{ $eyebrow }}</p>

                        <div class="space-y-4">
                            <div class="relative inline-flex">
                                <div class="absolute inset-0 blur-3xl {{ $theme['glow'] }}"></div>
                                <div class="relative bg-gradient-to-r {{ $theme['code'] }} bg-clip-text text-7xl font-semibold tracking-[-0.08em] text-transparent sm:text-8xl lg:text-9xl">
                                    {{ $code }}
                                </div>
                            </div>

                            <h1 class="max-w-3xl text-4xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-5xl lg:text-6xl">
                                {{ $title }}
                            </h1>

                            <p class="max-w-2xl text-lg leading-8 text-slate-600 dark:text-zinc-300">
                                {{ $message }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <a href="{{ $primaryUrl }}" class="app-button-primary">
                                {{ $primaryLabel }}
                            </a>

                            @if ($secondaryLabel && $secondaryUrl)
                                <a href="{{ $secondaryUrl }}" class="app-button-secondary">
                                    {{ $secondaryLabel }}
                                </a>
                            @endif
                        </div>
                    </section>

                    <section class="rounded-[2rem] border p-6 shadow-[0_24px_80px_rgba(15,23,42,0.12)] backdrop-blur sm:p-8 {{ $theme['panel'] }}">
                        <div class="welcome-login-card">
                            <div class="flex items-center gap-4">
                                <x-app-logo-icon class="h-16 w-16 shadow-lg" />
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-emerald-700">Service status</p>
                                    <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ config('rhu.name') }}</h2>
                                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-zinc-300">{{ config('rhu.system_name') }}</p>
                                </div>
                            </div>

                            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                <div class="app-panel border-white/80 bg-white/85 backdrop-blur dark:border-white/10 dark:bg-zinc-900/80">
                                    <p class="eyebrow">What happened</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-zinc-300">
                                        The request reached the server, but it could not be completed normally.
                                    </p>
                                </div>

                                <div class="app-panel border-white/80 bg-white/85 backdrop-blur dark:border-white/10 dark:bg-zinc-900/80">
                                    <p class="eyebrow">What you can do</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-zinc-300">
                                        Return to a safe page, sign in again if needed, or retry after a short wait.
                                    </p>
                                </div>
                            </div>

                            <div class="mt-6 rounded-[1.5rem] border border-dashed border-slate-300/80 bg-white/70 p-5 dark:border-white/10 dark:bg-zinc-950/40">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-zinc-400">Current response</p>
                                        <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">{{ $code }} / {{ $title }}</p>
                                    </div>

                                    <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] {{ $theme['badge'] }}">
                                        Available actions
                                    </span>
                                </div>

                                <div class="mt-4 space-y-3 text-sm leading-6 text-slate-600 dark:text-zinc-300">
                                    <p>Use the main button to return to a working page in the portal.</p>
                                    <p>If this keeps happening, RHU staff may need to review the request or system status.</p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
</body>

</html>
