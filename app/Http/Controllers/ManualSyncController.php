<?php

namespace App\Http\Controllers;

use App\Models\SyncStatus;
use App\Services\OfflineSyncService;
use Illuminate\Http\RedirectResponse;

class ManualSyncController extends Controller
{
    public function store(OfflineSyncService $offlineSync): RedirectResponse
    {
        abort_unless(auth()->user()->isBarangayAdmin(), 403);

        $status = SyncStatus::firstOrCreate(['scope' => 'global']);
        $status->update(['state' => 'running', 'last_attempted_at' => now(), 'last_error' => null]);

        try {
            $result = $offlineSync->syncPending();
            $status->update(['state' => $result['failed'] > 0 ? 'degraded' : 'healthy', 'last_synced_by' => auth()->id(), 'last_synced_at' => now(), 'last_processed' => $result['processed'], 'last_failed' => $result['failed']]);
        } catch (\Throwable $exception) {
            report($exception);
            $status->update(['state' => 'failed', 'last_failed' => $status->last_failed + 1, 'last_error' => $exception->getMessage()]);
            return to_route('sync.index')->withErrors(['sync' => 'Synchronization failed. Queued local data was preserved for retry.']);
        }

        return to_route('sync.index')->with('status', 'Sync completed. Processed '.$result['processed'].' item(s) with '.$result['failed'].' failure(s).');
    }
}
