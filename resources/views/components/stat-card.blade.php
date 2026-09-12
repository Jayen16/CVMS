@props(['label', 'value', 'href' => null])

@php
    $normalizedLabel = \Illuminate\Support\Str::lower($label);
@endphp

@if ($href)
    <a href="{{ $href }}" class="app-card block p-5 transition hover:-translate-y-0.5 hover:border-teal-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2 dark:hover:border-teal-700 dark:focus:ring-offset-zinc-950" wire:navigate>
@else
    <div class="app-card p-5">
@endif
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500 dark:text-zinc-400">{{ $label }}</p>
            <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $value }}</p>
        </div>
        <div class="flex size-10 items-center justify-center rounded-lg bg-teal-50 text-teal-700 ring-1 ring-teal-100 dark:bg-teal-950 dark:text-teal-300 dark:ring-teal-900">
            @if (\Illuminate\Support\Str::contains($normalizedLabel, 'barangay admin'))
                <flux:icon.shield-check class="size-5" />
            @elseif (\Illuminate\Support\Str::contains($normalizedLabel, 'barangay'))
                <flux:icon.map-pin class="size-5" />
            @elseif (\Illuminate\Support\Str::contains($normalizedLabel, 'nurse'))
                <flux:icon.user-plus class="size-5" />
            @elseif (\Illuminate\Support\Str::contains($normalizedLabel, 'children') || \Illuminate\Support\Str::contains($normalizedLabel, 'child'))
                <flux:icon.users class="size-5" />
            @elseif (\Illuminate\Support\Str::contains($normalizedLabel, 'vaccination'))
                <flux:icon.heart class="size-5" />
            @elseif (\Illuminate\Support\Str::contains($normalizedLabel, 'pending sync'))
                <flux:icon.arrow-path class="size-5" />
            @elseif (\Illuminate\Support\Str::contains($normalizedLabel, 'pending'))
                <flux:icon.clock class="size-5" />
            @else
                <flux:icon.chart-bar class="size-5" />
            @endif
        </div>
    </div>
@if ($href)
    </a>
@else
    </div>
@endif
