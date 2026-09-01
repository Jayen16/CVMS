<?php

namespace App\Livewire;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogsPage extends Component
{
    use WithPagination;

    public string $search = '';

    public string $event = 'all';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedEvent(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->canViewOversight(), 403);

        $logs = AuditLog::query()
            ->with('user')
            ->when(! auth()->user()->isSuperAdmin(), fn ($query) => $query->whereHas('user', fn ($user) => $user->where('barangay_id', auth()->user()->barangay_id)))
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('description', 'like', '%'.$this->search.'%')
                        ->orWhere('auditable_type', 'like', '%'.$this->search.'%')
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->event !== 'all', fn ($query) => $query->where('event', $this->event))
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->paginate(20);

        return view('audit-logs.index', compact('logs'))->layout('layouts.app', ['title' => 'Audit Logs']);
    }
}
