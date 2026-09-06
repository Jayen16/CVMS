<?php

namespace App\Services;

class FacilitySyncService
{
    /** @return array{pulled: int, pushed: int, failed: int, pull_batch_uuid: string} */
    public function synchronize(): array
    {
        $installation = app(FacilityActivationService::class)->localInstallation();
        abort_unless($installation->status === 'active', 422, 'This facility connection is '.$installation->status.'.');

        try {
            $pulled = app(FacilityPullSyncService::class)->synchronize();
            $pushed = app(FacilityPushSyncService::class)->synchronize();
        } catch (\Throwable $exception) {
            if (in_array((int) $exception->getCode(), [401, 403], true)) {
                $installation->update(['status' => 'suspended']);
            }

            throw $exception;
        }

        return [
            'pulled' => $pulled['processed'],
            'pushed' => $pushed['processed'],
            'failed' => $pushed['failed'],
            'pull_batch_uuid' => $pulled['batch_uuid'],
        ];
    }
}
