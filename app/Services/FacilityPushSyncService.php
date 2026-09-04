<?php

namespace App\Services;

use App\Models\OfflineSyncOutbox;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class FacilityPushSyncService
{
    /** @return array{processed: int, failed: int} */
    public function synchronize(): array
    {
        $installation = app(FacilityActivationService::class)->localInstallation();
        abort_unless($installation->status === 'active', 422, 'This facility is not activated.');

        $queue = app(OfflineSyncService::class);
        User::query()->whereIn('role', ['barangay_admin', 'nurse', 'midwife', 'inventory_staff', 'bhw'])->get()->each($queue->queueStaff(...));

        $rows = OfflineSyncOutbox::query()
            ->whereIn('status', ['pending', 'failed'])
            ->whereIn('entity', ['facility_staff', 'children', /* 'child_transfers', */ 'immunization_records', 'guardians', 'child_guardian_relationships', 'inventory_transactions', 'appointments', 'audit_events', 'notification_requests'])
            ->where(function ($query): void {
                $query->where('status', 'pending')->orWhere(function ($failed): void {
                    $failed->where('status', 'failed')->where(function ($retry): void {
                        $retry->whereNull('last_attempted_at')->orWhere('last_attempted_at', '<=', now()->subSeconds($this->retryDelay()));
                    });
                });
            })
            ->orderBy('queued_at')
            ->limit(50)
            ->get();

        if ($rows->isEmpty()) {
            return ['processed' => 0, 'failed' => 0];
        }

        $rows->each(fn (OfflineSyncOutbox $row) => $row->update([
            'status' => 'processing', 'attempts' => $row->attempts + 1, 'last_attempted_at' => now(),
        ]));

        try {
            $token = Http::asForm()->timeout(15)->withBasicAuth($installation->passport_client_id, $installation->passport_client_secret)->post(rtrim((string) $installation->central_url, '/').'/oauth/token', [
                'grant_type' => 'client_credentials',
                'scope' => 'sync:push',
            ])->throw()->json('access_token');

            $response = Http::acceptJson()->withToken($token)->timeout(30)->post(rtrim((string) $installation->central_url, '/').'/api/v1/sync/push', [
                'events' => $rows->map(fn (OfflineSyncOutbox $row): array => [
                    'event_uuid' => $row->event_uuid,
                    'entity' => $row->entity,
                    'record_uuid' => $row->model_sync_uuid,
                    'operation' => $row->operation,
                    'version' => $row->version,
                    'data' => $row->payload,
                ])->all(),
            ])->throw();

            $accepted = collect($response->json('accepted', []))->flip();
            $rows->each(function (OfflineSyncOutbox $row) use ($accepted): void {
                if ($accepted->has($row->event_uuid)) {
                    $row->update(['status' => 'synced', 'synced_at' => now(), 'synchronized_at' => now(), 'last_error' => null]);
                } else {
                    $row->update(['status' => 'failed', 'last_error' => 'Central did not acknowledge this event.']);
                }
            });

            return ['processed' => $accepted->count(), 'failed' => $rows->count() - $accepted->count()];
        } catch (\Throwable $exception) {
            $rows->each(fn (OfflineSyncOutbox $row) => $row->update(['status' => 'failed', 'last_error' => $exception->getMessage()]));
            throw $exception;
        }
    }

    private function retryDelay(): int
    {
        $attempts = (int) OfflineSyncOutbox::query()->where('status', 'failed')->max('attempts');
        $base = max(1, (int) config('system.sync_retry_base_seconds', 60));
        $max = max($base, (int) config('system.sync_retry_max_seconds', 3600));

        return min($max, $base * (2 ** min($attempts, 10)));
    }
}
