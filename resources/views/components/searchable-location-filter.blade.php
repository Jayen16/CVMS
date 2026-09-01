@props(['label', 'name', 'options' => [], 'value' => null])

@php
    $items = collect($options)->map(fn ($text, $id) => ['id' => (string) $id, 'label' => (string) $text])->values();
    $selected = $items->firstWhere('id', (string) $value);
@endphp

<div
    class="relative grid gap-2 text-sm"
    x-data="{ open: false, search: @js($selected['label'] ?? ''), selected: @js((string) ($value ?? '')), items: @js($items) }"
    @click.outside="open = false"
>
    <span class="font-medium text-slate-800 dark:text-zinc-100">{{ $label }}</span>
    <div class="relative">
        <input
            type="text"
            x-model="search"
            @input="selected = items.find(item => item.label === search)?.id ?? ''"
            @focus="open = true"
            @keydown.escape="open = false"
            placeholder="Search {{ strtolower($label) }}..."
            autocomplete="off"
            class="app-input w-full pe-10"
        >
        <button type="button" @click="open = !open" class="absolute inset-y-0 end-0 flex w-10 items-center justify-center text-zinc-500" aria-label="Toggle {{ $label }} options">
            <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.51a.75.75 0 01-1.08 0l-4.25-4.51a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
        </button>
        <input type="hidden" name="{{ $name }}" x-model="selected">
        <div x-show="open" x-cloak class="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-slate-300 bg-white p-1 shadow-xl dark:border-zinc-600 dark:bg-zinc-900">
            <button type="button" @click="selected = ''; search = ''; open = false; setTimeout(() => $el.closest('form')?.requestSubmit(), 0)" class="block w-full rounded-md px-3 py-2 text-start text-sm text-zinc-500 hover:bg-slate-100 dark:hover:bg-zinc-800">All {{ strtolower($label) }} options</button>
            <template x-for="item in items.filter(item => item.label.toLowerCase().includes(search.toLowerCase()))" :key="item.id">
                <button type="button" @click="selected = item.id; search = item.label; open = false; setTimeout(() => $el.closest('form')?.requestSubmit(), 0)" class="block w-full rounded-md px-3 py-2 text-start text-sm text-slate-800 hover:bg-teal-50 dark:text-zinc-100 dark:hover:bg-zinc-800" x-text="item.label"></button>
            </template>
            <p x-show="items.filter(item => item.label.toLowerCase().includes(search.toLowerCase())).length === 0" class="px-3 py-2 text-sm text-zinc-500">No matches found.</p>
        </div>
    </div>
</div>
