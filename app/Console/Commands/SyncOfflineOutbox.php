<?php

namespace App\Console\Commands;

use App\Services\OfflineSyncService;
use Illuminate\Console\Command;

class SyncOfflineOutbox extends Command
{
    protected $signature = 'offline:sync-outbox';

    protected $description = 'Replay locally queued SQLite changes into the configured MySQL connection.';

    public function handle(OfflineSyncService $sync): int
    {
        if (! $sync->shouldQueue()) {
            $this->info('Offline sync is disabled.');

            return self::SUCCESS;
        }

        $result = $sync->syncPending();

        $this->info("Offline sync complete. {$result['processed']} processed, {$result['failed']} failed.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
