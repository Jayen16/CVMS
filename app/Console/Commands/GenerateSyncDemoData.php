<?php

namespace App\Console\Commands;

use App\Models\ChildProfile;
use App\Models\SyncStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class GenerateSyncDemoData extends Command
{
    public const FACILITY_UUID = 'de000000-0000-4000-8000-000000000001';

    protected $signature = 'cvms:generate-sync-demo {--dry-run : Roll back the sample history}';

    protected $description = 'Add clearly labeled Central sync history for existing dummy children';

    public function handle(): int
    {
        if (config('system.instance_type') !== 'central') {
            $this->error('This command adds demo history to a Central instance.');

            return self::FAILURE;
        }

        // Take one child per barangay so the demonstration covers several facilities.
        $children = ChildProfile::query()->where('address', 'like', 'CVMS dummy population v1%')
            ->whereNotNull('sync_uuid')->orderBy('barangay_id')->get()
            ->unique('barangay_id')->take(20);

        if ($children->isEmpty()) {
            $this->error('Generate the dummy population first. No sample children were found.');

            return self::FAILURE;
        }

        DB::beginTransaction();
        try {
            $finished = now()->startOfSecond();
            $realStatus = SyncStatus::where('scope', 'global')->first();
            if ($realStatus?->last_attempted_at && $realStatus->last_synced_at) {
                $finished = $realStatus->last_synced_at;
            }

            foreach ($children as $child) {
                foreach (['created', 'updated'] as $operation) {
                    $eventId = (string) Uuid::uuid5(Uuid::NAMESPACE_DNS, 'cvms-sync-demo:'.$child->sync_uuid.':'.$operation);
                    DB::table('sync_processed_events')->updateOrInsert(['event_uuid' => $eventId], [
                        'id' => $eventId,
                        'facility_id' => self::FACILITY_UUID,
                        'entity' => 'children',
                        'record_uuid' => $child->sync_uuid,
                        'operation' => $operation,
                        'version' => $operation === 'created' ? 1 : 2,
                        'outcome' => 'applied',
                        'applied_at' => $finished,
                        'created_at' => $finished,
                        'updated_at' => $finished,
                    ]);
                }
            }

            // Keep the real sync status intact; the page uses this only before a real sync.
            SyncStatus::updateOrCreate(['scope' => 'demo'], [
                'state' => 'healthy',
                'last_attempted_at' => $finished->copy()->subMinute(),
                'last_synced_at' => $finished,
                'last_processed' => $children->count() * 2,
                'last_failed' => 0,
            ]);

            if ($this->option('dry-run')) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $this->info(($this->option('dry-run') ? 'Dry run: ' : '').($children->count() * 2).' demo sync history entries prepared. No data was transmitted.');

        return self::SUCCESS;
    }
}
