<div class="app-page">
    <div wire:loading.flex class="fixed inset-x-0 top-0 z-[60] items-center justify-center gap-2 bg-teal-700 px-4 py-2 text-sm font-medium text-white shadow-lg" role="status" aria-live="polite">
        <span class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-white"></span> Updating notifications…
    </div>

    <div class="page-heading">
        <div>
            <p class="eyebrow">Your inbox</p>
            <h1 class="page-title">Notifications</h1>
            <p class="page-subtitle">Stay up to date with important account activity and follow-up actions.</p>
        </div>
    </div>

    <section class="app-card mt-2 overflow-visible">
        <div class="app-card-header flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="app-card-title">All notifications</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Use the filters to find updates from a specific period.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                <div class="flex flex-wrap items-end gap-2">
                    <label class="grid min-w-32 gap-1 text-xs font-medium text-slate-500 dark:text-zinc-400">
                        From
                        <input type="date" wire:model.live.debounce.400ms="from" class="app-input !py-1.5 text-xs">
                    </label>
                    <label class="grid min-w-32 gap-1 text-xs font-medium text-slate-500 dark:text-zinc-400">
                        To
                        <input type="date" wire:model.live.debounce.400ms="to" class="app-input !py-1.5 text-xs">
                    </label>
                    @if ($from || $to)
                        <button type="button" wire:click="clearDateFilter" class="mb-1 text-xs font-semibold text-teal-700 hover:underline dark:text-teal-300">Clear dates</button>
                    @endif
                </div>
                <label class="flex min-h-10 items-center gap-2 rounded-xl border border-slate-200 px-3 text-sm font-medium text-slate-600 dark:border-zinc-700 dark:text-zinc-300">
                    <input type="checkbox" wire:model.live.debounce.400ms="unreadOnly" class="rounded border-slate-300 text-teal-600 focus:ring-teal-600">
                    Unread only
                </label>
                <button wire:click="markAllRead" @disabled($unreadCount === 0) class="app-button-secondary disabled:cursor-not-allowed disabled:opacity-50">Mark all as read</button>
            </div>
        </div>

        <div class="divide-y divide-slate-100 dark:divide-zinc-800">
            @forelse ($notifications as $notification)
                <a href="{{ route('notifications.read', $notification, false) }}" class="group relative flex gap-3 px-5 py-4 transition hover:bg-teal-50/60 sm:gap-4 sm:px-6 {{ $notification->read_at ? '' : 'bg-teal-50/35 dark:bg-teal-950/10' }} dark:hover:bg-zinc-800">
                    <div class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl {{ $notification->read_at ? 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-400' : 'bg-teal-100 text-teal-700 dark:bg-teal-950 dark:text-teal-300' }}">
                        <flux:icon :icon="$notification->data['icon'] ?? 'bell'" class="size-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold text-slate-950 dark:text-white">{{ $notification->data['title'] ?? 'Notification' }}</p>
                            <time datetime="{{ $notification->created_at->toIso8601String() }}" title="{{ $notification->created_at->format('F j, Y g:i A') }}" class="text-xs text-slate-500 dark:text-zinc-400">{{ $notification->created_at->diffForHumans() }}</time>
                        </div>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600 dark:text-zinc-300">{{ $notification->data['body'] ?? '' }}</p>
                        @if (! $notification->read_at)
                            <span class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-teal-700 dark:text-teal-300"><span class="size-1.5 rounded-full bg-teal-600"></span>Unread</span>
                        @endif
                    </div>
                    <flux:icon.chevron-right class="mt-2 hidden size-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-teal-600 sm:block" />
                </a>
            @empty
                <div class="px-5 py-16 text-center sm:px-6">
                    <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-zinc-800 dark:text-zinc-500"><flux:icon.bell-slash class="size-7" /></span>
                    <h3 class="mt-4 font-semibold text-slate-950 dark:text-white">{{ $unreadOnly || $from || $to ? 'No matching notifications' : 'You’re all caught up' }}</h3>
                    <p class="mx-auto mt-1 max-w-sm text-sm leading-6 text-slate-500 dark:text-zinc-400">{{ $unreadOnly || $from || $to ? 'Try adjusting your filters to see more updates.' : 'New account updates and actions will appear here.' }}</p>
                    @if ($unreadOnly || $from || $to)
                        <button type="button" wire:click="clearFilters" class="mt-4 text-sm font-semibold text-teal-700 hover:underline dark:text-teal-300">Clear filters</button>
                    @endif
                </div>
            @endforelse
        </div>
    </section>

    @if ($notifications->hasPages())
        <div class="mt-1">{{ $notifications->links() }}</div>
    @endif
</div>
