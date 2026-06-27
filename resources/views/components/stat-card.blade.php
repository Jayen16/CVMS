@props(['label', 'value'])

<div class="app-card p-5">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500 dark:text-zinc-400">{{ $label }}</p>
            <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $value }}</p>
        </div>
        <div class="size-10 rounded-lg bg-teal-50 ring-1 ring-teal-100 dark:bg-teal-950 dark:ring-teal-900"></div>
    </div>
</div>
