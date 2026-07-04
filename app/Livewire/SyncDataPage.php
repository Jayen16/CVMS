<?php

namespace App\Livewire;

use App\Models\OfflineSyncOutbox;
use App\Models\SyncStatus;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SyncDataPage extends Component
{
    public function render(): View
    {
        abort_unless(
            auth()->user()->isSuperAdmin() || auth()->user()->isBarangayAdmin() || auth()->user()->isNurse(),
            403
        );

        $latestStatus = SyncStatus::query()
            ->with('user')
            ->where('scope', 'global')
            ->first();

        return view('livewire.sync-data-page', [
            'latestStatus' => $latestStatus,
            'pendingCount' => config('offline.enabled')
                ? OfflineSyncOutbox::whereNull('synced_at')->count()
                : 0,
            'recentRows' => OfflineSyncOutbox::query()
                ->latest('queued_at')
                ->take(25)
                ->get(),
        ])->layout('layouts.app', [
            'title' => 'Sync Data',
        ]);
    }
}
