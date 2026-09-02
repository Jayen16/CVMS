<?php

namespace App\Console\Commands;

use App\Services\FacilityPullSyncService;
use Illuminate\Console\Command;

class SyncCentralData extends Command
{
    protected $signature = 'sync:central';

    protected $description = 'Pull central-owned master data into the local facility database.';

    public function handle(FacilityPullSyncService $sync): int
    {
        if (config('system.instance_type') !== 'facility') {
            $this->info('Central pull is disabled on the central instance.');

            return self::SUCCESS;
        }

        try {
            $result = $sync->synchronize();
            $this->info("Central sync complete. {$result['processed']} records processed.");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Central sync failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
