<div class="app-page">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="page-title">Notifications</h1>
            <p class="page-subtitle">Updates and actions relevant to your account.</p>
        </div>
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-zinc-300">
                <input type="checkbox" wire:model.live="unreadOnly" class="rounded border-slate-300 text-teal-600">
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
