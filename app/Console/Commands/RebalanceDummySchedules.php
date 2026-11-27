<?php

namespace App\Console\Commands;

use App\Models\ChildProfile;
use App\Models\ChildVaccineSeriesVersion;
use App\Models\VaccinationRecord;
use App\Models\VaccineInventoryItem;
use App\Models\VaccineInventoryTransaction;
use App\Models\VaccineSchedule;
use App\Services\DummyVaccinationTimeline;
use App\Services\ImmunizationSuggestionService;
use App\Services\VaccineScheduleVersionResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RebalanceDummySchedules extends Command
{
    protected $signature = 'cvms:rebalance-dummy-schedules {--dry-run}';

    protected $description = 'Rebalance only generated child timelines, preserving accounts and current stock balances';

    public function handle(): int
    {
        $version = app(VaccineScheduleVersionResolver::class)->activeVersion();
        $schedules = VaccineSchedule::where('vaccine_schedule_version_id', $version?->id)->where('active', true)
            ->whereHas('vaccineType', fn ($query) => $query->where('active', true))->get();
        $children = ChildProfile::where('address', 'like', 'CVMS dummy population v1%')->orderBy('barangay_id')->orderBy('id')->get();
        if ($children->isEmpty() || $schedules->count() < 11) {
            $this->error('Generated children and an active schedule are required.');

            return self::FAILURE;
        }
        $items = VaccineInventoryItem::where('notes', 'like', 'CVMS dummy population v1%')->get();
        $balances = $items->mapWithKeys(fn ($item) => [$item->id => $item->availableStock()]);
        $itemMap = $items->keyBy(fn ($item) => $item->barangay_id.':'.$item->vaccine_type_id);
        $statuses = [];
        $maxLate = 0;
        $histories = 0;
        $pending = 0;
        DB::beginTransaction();
        try {
            Model::withoutEvents(function () use ($children, $schedules, $version, $itemMap, &$statuses, &$maxLate, &$histories, &$pending): void {
                $indexes = [];
                foreach ($children as $child) {
                    $index = $indexes[$child->barangay_id] ?? 0;
                    $indexes[$child->barangay_id] = $index + 1;
                    $old = $child->vaccinations()->orderBy('administered_at')->orderBy('id')->get();
                    if ($old->isEmpty() || $old->contains(fn ($record) => ! str_starts_with($record->remarks ?? '', 'CVMS dummy population v1') || $record->proofPaths() !== [])) {
                        throw new \RuntimeException('Child has non-generated or proof-bearing records; refusing to overwrite: '.$child->id);
                    }
                    $plan = app(DummyVaccinationTimeline::class)->plan($schedules, $index);
                    $parentId = $old->firstWhere('verification_status', 'pending')?->submitted_by;
                    $nurseId = $child->created_by;
                    $template = $old->first()->getAttributes();
                    unset($template['id'], $template['created_at'], $template['updated_at'], $template['sync_uuid']);
                    VaccineInventoryTransaction::whereIn('vaccination_record_id', $old->modelKeys())->delete();
                    ChildVaccineSeriesVersion::where('child_profile_id', $child->id)->delete();
                    $child->update(['birthdate' => $plan['birthdate'], 'sync_version' => $child->sync_version + 1]);
                    foreach ($plan['schedules'] as $i => $schedule) {
                        $isPending = $parentId !== null && $i === $plan['schedules']->count() - 1;
                        $date = $plan['dates'][$i];
                        $record = $old[$i] ?? new VaccinationRecord($template);
                        $record->forceFill([
                            'vaccine_type_id' => $schedule->vaccine_type_id, 'dose_number' => $schedule->dose_number,
                            'administered_at' => $date, 'recorded_by' => $isPending ? $parentId : $nurseId,
                            'submitted_by' => $isPending ? $parentId : null, 'verified_by' => $isPending ? null : $nurseId,
                            'verified_at' => $isPending ? null : $date->copy()->setTime(10, 0),
                            'source' => $isPending ? 'outside_clinic' : 'barangay_clinic',
                            'verification_status' => $isPending ? 'pending' : 'verified',
                            'clinic_name' => $isPending ? 'Dummy Outside Clinic' : trim($child->barangay->name).' Health Station',
                            'client_submission_id' => $isPending ? 'rebalanced-'.Str::uuid() : null,
                            'sync_uuid' => $record->sync_uuid ?: (string) Str::uuid(),
                            'sync_version' => (int) $record->sync_version + 1,
                        ])->save();
                        ChildVaccineSeriesVersion::firstOrCreate([
                            'child_profile_id' => $child->id, 'vaccine_type_id' => $schedule->vaccine_type_id,
                        ], ['vaccine_schedule_version_id' => $version->id, 'assigned_at' => $date, 'assignment_reason' => 'dummy_population_seed']);
                        if ($isPending) {
                            $pending++;
                        } else {
                            $item = $itemMap->get($child->barangay_id.':'.$schedule->vaccine_type_id);
                            if (! $item) {
                                throw new \RuntimeException('Missing generated inventory batch.');
                            }
                            VaccineInventoryTransaction::create([
                                'barangay_id' => $child->barangay_id, 'vaccine_type_id' => $schedule->vaccine_type_id,
                                'vaccine_inventory_item_id' => $item->id, 'vaccination_record_id' => $record->id,
                                'recorded_by' => $nurseId, 'transaction_type' => 'usage', 'movement' => 'out', 'quantity' => 1,
                                'batch_number' => $item->batch_number, 'expiry_date' => $item->expiry_date,
                                'transaction_date' => $date, 'reference_number' => $record->id,
                                'sync_uuid' => (string) Str::uuid(), 'notes' => 'CVMS dummy population v1 - rebalanced timeline',
                            ]);
                        }
                        $histories++;
                    }
                    VaccinationRecord::whereIn('id', $old->slice($plan['schedules']->count())->modelKeys())->delete();
                    $child = $child->fresh();
                    $suggestion = app(ImmunizationSuggestionService::class)->suggestNextDose($child);
                    $late = max(0, (int) $suggestion['due_at']->diffInDays(Carbon::today(), false));
                    if ($late > 60) {
                        throw new \RuntimeException('Generated schedule exceeds 60 days overdue.');
                    }
                    $maxLate = max($maxLate, $late);
                    $statuses[$suggestion['status']] = ($statuses[$suggestion['status']] ?? 0) + 1;
                    $child->vaccinations()->update(app(ImmunizationSuggestionService::class)->suggestionForRecord($child));
                    if (($index + 1) % 25 === 0) {
                        $this->line($child->barangay->name.': '.($index + 1).' children rebalanced');
                    }
                }
            });
            // Retain the user's existing shortage/surplus mix after rewriting usage.
            foreach ($items as $item) {
                $difference = $balances[$item->id] - $item->availableStock();
                if ($difference !== 0) {
                    VaccineInventoryTransaction::withoutEvents(fn () => VaccineInventoryTransaction::create([
                        'barangay_id' => $item->barangay_id, 'vaccine_type_id' => $item->vaccine_type_id,
                        'vaccine_inventory_item_id' => $item->id, 'recorded_by' => $children->firstWhere('barangay_id', $item->barangay_id)->created_by,
                        'transaction_type' => 'adjustment', 'movement' => $difference > 0 ? 'in' : 'out', 'quantity' => abs($difference),
                        'transaction_date' => today(), 'sync_uuid' => (string) Str::uuid(),
                        'notes' => 'CVMS dummy population v1 - preserve balance after timeline rebalance',
                    ]));
                }
                if ($item->availableStock() !== $balances[$item->id]) {
                    throw new \RuntimeException('Inventory balance changed unexpectedly.');
                }
            }
            $result = ['children' => $children->count(), 'statuses' => $statuses, 'max_days_overdue' => $maxLate, 'histories' => $histories, 'pending' => $pending];
            if ($this->option('dry-run')) {
                DB::rollBack();
            } else {
                DB::commit();
            }
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return self::SUCCESS;
    }
}
