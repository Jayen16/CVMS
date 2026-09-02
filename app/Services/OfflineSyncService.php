<?php

namespace App\Services;

use App\Models\OfflineSyncOutbox;
use Illuminate\Database\Eloquent\Model;

/**
 * Queues local facility-owned changes for the future facility-to-central push phase.
 */
class OfflineSyncService
{
    public function shouldQueue(): bool
    {
        return (bool) config('offline.enabled');
    }

    public function queueUpsert(Model $model): void
    {
        if (! $this->shouldQueue()) {
            return;
        }

        OfflineSyncOutbox::create([
            'model_type' => $model::class,
            'model_sync_uuid' => $model->sync_uuid,
            'operation' => 'upsert',
            'payload' => ['sync_uuid' => $model->sync_uuid],
            'queued_at' => now(),
        ]);
    }

    public function queueDelete(Model $model): void
    {
        if (! $this->shouldQueue()) {
            return;
        }

        OfflineSyncOutbox::create([
            'model_type' => $model::class,
            'model_sync_uuid' => $model->sync_uuid,
            'operation' => 'delete',
            'payload' => ['sync_uuid' => $model->sync_uuid],
            'queued_at' => now(),
        ]);
    }

    /**
     * Compatibility entry point for the existing manual-sync route.
     * Direct remote-database replay has been removed; this performs central pull.
     *
     * @return array{processed: int, failed: int}
     */
    public function syncPending(): array
    {
        try {
            $result = app(FacilityPullSyncService::class)->synchronize();

            return ['processed' => $result['processed'], 'failed' => 0];
        } catch (\Throwable $exception) {
            report($exception);

            return ['processed' => 0, 'failed' => 1];
        }
    }
}
