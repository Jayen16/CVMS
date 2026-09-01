@props(['compact' => false, 'drawerOnly' => false])

@php
    $notificationPreview = auth()->user()->notifications()->latest()->limit(5)->get();
    $unreadNotifications = auth()->user()->unreadNotifications()->count();
@endphp

@if (! $drawerOnly)
<flux:modal.trigger name="notifications-drawer">
    <button type="button" aria-label="Notifications" class="relative flex items-center gap-3 rounded-xl border border-slate-200 bg-white/90 p-1.5 text-start text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900/85 dark:text-zinc-200 dark:hover:bg-zinc-800 {{ $compact ? 'w-auto px-2.5' : 'w-full' }}">
        <span class="flex items-center gap-3 rounded-lg px-2.5 py-1.5 {{ ! $compact ? 'flex-1' : '' }} {{ $unreadNotifications > 0 ? 'bg-teal-50 text-teal-900 dark:bg-teal-950/70 dark:text-teal-100' : '' }}">
            <flux:icon name="bell" class="size-5 shrink-0 {{ $unreadNotifications > 0 ? 'text-teal-700 dark:text-teal-300' : 'text-slate-500 dark:text-zinc-400' }}" />
            <span class="{{ $compact ? 'hidden' : 'flex-1' }}">Notifications</span>
        </span>
        @if ($unreadNotifications > 0)
            <span class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-rose-600 px-1.5 text-[10px] font-bold leading-none text-white shadow-sm">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
        @endif
    </button>
</flux:modal.trigger>
@endif

@if ($drawerOnly)
<flux:modal name="notifications-drawer" variant="flyout" position="right" class="!h-[36rem] !max-h-[calc(100dvh-2rem)] !w-[min(100vw,28rem)] !overflow-hidden !p-0" :closable="false">
    <div class="flex h-full min-h-0 w-full flex-col overflow-hidden bg-white dark:bg-zinc-900">
        <div class="flex items-start justify-between border-b border-slate-200 bg-slate-50/80 px-6 py-5 dark:border-zinc-800 dark:bg-zinc-950/60">
            <div>
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Notifications</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    {{ $unreadNotifications }} unread {{ $unreadNotifications === 1 ? 'update' : 'updates' }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('notifications.index') }}" class="hidden text-xs font-semibold text-teal-700 hover:underline sm:inline dark:text-teal-300">View all</a>
                <flux:modal.close>
                    <flux:button variant="ghost" icon="x-mark" size="sm" aria-label="Close notifications" />
                </flux:modal.close>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-hidden py-2">
            @forelse ($notificationPreview as $notification)
                <a href="{{ route('notifications.read', $notification) }}" class="mx-4 my-2 block rounded-lg border border-slate-200 px-4 py-4 transition hover:border-teal-300 hover:bg-teal-50/60 dark:border-zinc-800 dark:hover:border-teal-800 dark:hover:bg-zinc-800 {{ $notification->read_at ? 'opacity-70' : '' }}">
                    <div class="flex items-start gap-4">
                        <div class="mt-0.5 rounded-full bg-teal-100 p-2 text-teal-700 dark:bg-teal-950 dark:text-teal-300">
                            <flux:icon :icon="$notification->data['icon'] ?? 'bell'" class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <p class="font-semibold text-slate-950 dark:text-white">{{ $notification->data['title'] ?? 'Notification' }}</p>
                                @if (! $notification->read_at)
                                    <span class="mt-1 size-2 shrink-0 rounded-full bg-rose-600" aria-label="Unread"></span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm leading-5 text-slate-600 dark:text-zinc-300">{{ $notification->data['body'] ?? '' }}</p>
                            <time class="mt-2 block text-xs text-slate-500 dark:text-zinc-400">{{ $notification->created_at->diffForHumans() }}</time>
                        </div>
                    </div>
                </a>
            @empty
                <p class="px-6 py-12 text-center text-sm text-slate-500 dark:text-zinc-400">No notifications yet.</p>
            @endforelse
        </div>

        <div class="border-t border-slate-200 p-4 dark:border-zinc-800">
            <a href="{{ route('notifications.index') }}" class="app-button-secondary block w-full text-center" wire:navigate>View all notifications</a>
        </div>
    </div>
</flux:modal>
@endif
