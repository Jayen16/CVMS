<?php

namespace App\Console\Commands;

use App\Services\FacilitySyncService;
use Illuminate\Console\Command;

class SyncCentralData extends Command
{
    protected $signature = 'sync:central';

    protected $description = 'Pull central-owned master data into the local facility database.';

    public function handle(FacilitySyncService $sync): int
    {
        if (config('system.instance_type') !== 'facility') {
            $this->info('Central pull is disabled on the central instance.');

            return self::SUCCESS;
        }

        try {
            $result = $sync->synchronize();
            $this->info("Central sync complete. {$result['pulled']} pulled, {$result['pushed']} pushed, {$result['failed']} failed.");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Central sync failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
