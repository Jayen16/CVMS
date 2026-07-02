<?php

namespace App\Http\Controllers;

use App\Models\SyncStatus;
use App\Services\OfflineSyncService;
use Illuminate\Http\RedirectResponse;

class ManualSyncController extends Controller
{
    public function store(OfflineSyncService $offlineSync): RedirectResponse
    {
        abort_unless(auth()->user()->canManageBarangayStaff() || auth()->user()->isSuperAdmin(), 403);

        $result = $offlineSync->syncPending();

        SyncStatus::updateOrCreate(
            ['scope' => 'global'],
            [
                'last_synced_by' => auth()->id(),
                'last_synced_at' => now(),
                'last_processed' => $result['processed'],
                'last_failed' => $result['failed'],
            ]
        );

        return to_route('sync.index')->with('status', 'Sync completed. Processed '.$result['processed'].' item(s) with '.$result['failed'].' failure(s).');
    }
}
