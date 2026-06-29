@props(['label', 'value'])

@php
    $normalizedLabel = \Illuminate\Support\Str::lower($label);
@endphp

<div class="app-card p-5">
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
</div>
