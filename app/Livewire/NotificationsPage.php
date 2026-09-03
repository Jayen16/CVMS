<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Notifications')]
class NotificationsPage extends Component
{
    use WithPagination;

    public bool $unreadOnly = false;

    public function updatedUnreadOnly(): void
    {
        $this->resetPage();
    }

    public function markRead(string $notificationId): void
    {
        auth()->user()->notifications()->whereKey($notificationId)->update(['read_at' => now()]);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render(): View
    {
        $notifications = auth()->user()->notifications()
            ->when($this->unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate(15);

        return view('livewire.notifications-page', compact('notifications'))
            ->layout('layouts.app', ['title' => 'Notifications']);
    }
}
