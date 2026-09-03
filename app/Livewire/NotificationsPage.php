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

    public ?string $from = null;

    public ?string $to = null;

    public function updatedUnreadOnly(): void
    {
        $this->resetPage();
    }

    public function updatedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedTo(): void
    {
        $this->resetPage();
    }

    public function clearDateFilter(): void
    {
        $this->reset(['from', 'to']);
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
            ->when($this->from, fn ($query) => $query->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($query) => $query->whereDate('created_at', '<=', $this->to))
            ->latest()
            ->paginate(15);

        return view('livewire.notifications-page', compact('notifications'))
            ->layout('layouts.app', ['title' => 'Notifications']);
    }
}
