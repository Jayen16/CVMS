<div class="app-page">
    <div wire:loading.flex class="fixed inset-x-0 top-0 z-[60] items-center justify-center gap-2 bg-teal-700 px-4 py-2 text-sm font-medium text-white shadow-lg" role="status" aria-live="polite">
        <span class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-white"></span> Filtering data…
    </div>
    <div class="page-heading">
        <div>
            <p class="eyebrow">SYSTEM OVERSIGHT</p>
            <h1 class="page-title">Audit Logs</h1>
            <p class="page-subtitle">Review changes made to records and account activity across the system.</p>
        </div>
    </div>

    <section class="app-card overflow-hidden" x-data="{ filtersOpen: true }">
        <button
            type="button"
            @click="filtersOpen = !filtersOpen"
            class="flex w-full items-center justify-between px-5 py-4 text-left text-sm font-semibold transition hover:bg-slate-50 dark:hover:bg-zinc-900"
            :class="filtersOpen ? 'bg-teal-50/60 text-teal-800 dark:bg-teal-950/30 dark:text-teal-300' : 'text-slate-700 dark:text-zinc-200'"
            :aria-expanded="filtersOpen"
            aria-controls="audit-activity-filters"
        >
            <span>Activity filters</span>
            <span class="text-lg" x-text="filtersOpen ? '−' : '+'"></span>
        </button>

        <div id="audit-activity-filters" x-show="filtersOpen" role="region" aria-label="Activity filters" class="border-t border-slate-200 p-5 dark:border-zinc-800">
        <div class="space-y-4">
            <div class="flex flex-wrap items-end gap-3">
                <label class="min-w-52 flex-1 space-y-1.5"><span class="text-sm font-medium">Search</span><input wire:model.live.debounce.300ms="search" type="search" placeholder="User, action, or record" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"></label>
                <label class="space-y-1.5"><span class="text-sm font-medium">Action</span><select wire:model.live.debounce.400ms="event" class="min-w-40 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"><option value="all">All actions</option><option value="created">Created</option><option value="updated">Updated</option><option value="deleted">Deleted</option><option value="printed">Printed</option></select></label>
                <label class="space-y-1.5"><span class="text-sm font-medium">From</span><input wire:model.live.debounce.400ms="dateFrom" type="date" class="min-w-40 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"></label>
                <label class="space-y-1.5"><span class="text-sm font-medium">To</span><input wire:model.live.debounce.400ms="dateTo" type="date" class="min-w-40 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"></label>
            </div>
            <div class="basis-full">
                <x-location-filters
                    :regions="$regions"
                    :provinces="$provinces"
                    :municipalities="$municipalities"
                    :barangays="$barangays"
                    mode="wire"
                    :region-value="$regionId"
                    :province-value="$provinceId"
                    :municipality-value="$municipalityId"
                    :barangay-value="$barangayId"
                />
            </div>
        </div>
        </div>
    </section>

    <section class="app-card overflow-hidden">
        <div class="overflow-x-auto"><table class="app-table"><thead><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">User</th><th class="px-4 py-3">Action</th><th class="px-4 py-3">Record</th><th class="px-4 py-3">Details</th></tr></thead>
        <tbody>
        @forelse($logs as $log)
            <tr class="app-table-row">
                <td class="whitespace-nowrap">{{ $log->created_at->format('M d, Y h:i A') }}</td>
                <td class="font-medium text-slate-950 dark:text-white">{{ $log->actorName() }}</td>
                <td><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $log->event === 'deleted' ? 'bg-red-100 text-red-700' : ($log->event === 'created' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700') }}">{{ ucfirst($log->event) }}</span></td>
                <td>{{ $log->targetName() }}<div class="text-xs text-zinc-500">{{ $log->auditable_id }}</div></td>
                <td class="max-w-md text-sm">
                    <details>
                        <summary class="cursor-pointer">{{ $log->description }}</summary>
                        <div class="mt-3 space-y-3 text-xs text-zinc-500">
                            <div><span class="font-semibold">Previous values</span><pre class="mt-1 max-h-32 overflow-auto rounded bg-slate-50 p-2 dark:bg-zinc-900">{{ json_encode($log->old_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
                            <div><span class="font-semibold">New values</span><pre class="mt-1 max-h-32 overflow-auto rounded bg-slate-50 p-2 dark:bg-zinc-900">{{ json_encode($log->new_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
                            <dl class="space-y-1"><div><dt class="inline font-semibold">URL:</dt> <dd class="inline break-all">{{ $log->url ?: '—' }}</dd></div><div><dt class="inline font-semibold">IP address:</dt> <dd class="inline">{{ $log->ip_address ?: '—' }}</dd></div><div><dt class="inline font-semibold">User agent:</dt> <dd class="inline break-all">{{ $log->user_agent ?: '—' }}</dd></div></dl>
                        </div>
                    </details>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-10 text-center text-zinc-500">No audit activity matches these filters.</td></tr>
        @endforelse
        </tbody></table></div>
        @if($logs->hasPages()) <div class="border-t border-slate-100 p-4 dark:border-zinc-800">{{ $logs->links() }}</div> @endif
    </section>
</div>
