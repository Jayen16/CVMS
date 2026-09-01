<div class="app-page">
    <div wire:loading.flex class="fixed inset-x-0 top-0 z-[60] items-center justify-center gap-2 bg-teal-700 px-4 py-2 text-sm font-medium text-white shadow-lg" role="status" aria-live="polite">
        <span class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-white"></span> Filtering data…
    </div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="page-title">Notifications</h1>
            <p class="page-subtitle">Updates and actions relevant to your account.</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="flex flex-wrap items-end gap-2">
                <label class="grid gap-1 text-xs text-slate-500 dark:text-zinc-400">
                    From
                    <input type="date" wire:model.live.debounce.400ms="from" class="app-input !w-auto !py-1.5 text-xs">
                </label>
                <label class="grid gap-1 text-xs text-slate-500 dark:text-zinc-400">
                    To
                    <input type="date" wire:model.live.debounce.400ms="to" class="app-input !w-auto !py-1.5 text-xs">
                </label>
                @if ($from || $to)
                    <button type="button" wire:click="clearDateFilter" class="pb-1 text-xs font-semibold text-teal-700 hover:underline dark:text-teal-300">Clear</button>
                @endif
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-zinc-300">
                <input type="checkbox" wire:model.live.debounce.400ms="unreadOnly" class="rounded border-slate-300 text-teal-600">
                Unread only
            </label>
            <button wire:click="markAllRead" class="app-button-secondary">Mark all as read</button>
        </div>
    </div>

    <div class="app-panel mt-6 divide-y divide-slate-200 dark:divide-zinc-800">
        @forelse ($notifications as $notification)
            <a href="{{ route('notifications.read', $notification) }}" class="flex gap-4 px-5 py-4 transition hover:bg-teal-50/50 dark:hover:bg-zinc-800 {{ $notification->read_at ? 'opacity-70' : '' }}">
                <div class="mt-0.5 rounded-full bg-teal-100 p-2 text-teal-700 dark:bg-teal-950 dark:text-teal-300">
                    <flux:icon :icon="$notification->data['icon'] ?? 'bell'" class="size-4" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-semibold text-slate-950 dark:text-white">{{ $notification->data['title'] ?? 'Notification' }}</p>
                        <time class="text-xs text-slate-500">{{ $notification->created_at->diffForHumans() }}</time>
                    </div>
                    <p class="mt-1 text-sm text-slate-600 dark:text-zinc-300">{{ $notification->data['body'] ?? '' }}</p>
                    @if (! $notification->read_at)
                        <span class="mt-2 inline-block text-xs font-semibold text-teal-700 dark:text-teal-300">Unread</span>
                    @endif
                </div>
            </a>
        @empty
            <p class="px-5 py-10 text-center text-sm text-slate-500">You have no notifications.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $notifications->links() }}</div>
</div>
