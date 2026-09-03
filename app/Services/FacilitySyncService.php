<?php

namespace App\Services;

class FacilitySyncService
{
    /** @return array{pulled: int, pushed: int, failed: int} */
    public function synchronize(): array
    {
        $pulled = app(FacilityPullSyncService::class)->synchronize();
        $pushed = app(FacilityPushSyncService::class)->synchronize();

        return ['pulled' => $pulled['processed'], 'pushed' => $pushed['processed'], 'failed' => $pushed['failed']];
    }
}
