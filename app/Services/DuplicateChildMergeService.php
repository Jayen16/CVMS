<?php

namespace App\Services;

use App\Models\ChildProfile;
use App\Models\ChildVaccineSeriesVersion;
use App\Models\VaccinationReminder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DuplicateChildMergeService
{
    public function __construct(
        private readonly OfflineSyncService $offlineSync,
    ) {}

    /**
     * @param  Collection<int, ChildProfile>|iterable<int, ChildProfile>  $duplicates
     */
    public function mergeInto(ChildProfile $target, iterable $duplicates): void
    {
        $sources = collect($duplicates)
            ->filter(fn ($child) => $child instanceof ChildProfile && $child->id !== $target->id)
            ->unique('id')
            ->values();

        if ($sources->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($target, $sources): void {
            foreach ($sources as $source) {
                $this->mergeParents($target, $source);
                $this->moveVaccinations($target, $source);
                $this->moveAdverseEventReports($target, $source);
                $this->moveReminders($target, $source);
                $this->moveSeriesVersions($target, $source);

                $this->offlineSync->queueDelete($source);
                $source->delete();
            }
        });
    }

    private function mergeParents(ChildProfile $target, ChildProfile $source): void
    {
        $timestamp = now();

        foreach ($source->parents()->withPivot('relationship')->get() as $parent) {
            DB::table('child_parent')->updateOrInsert(
                [
                    'child_profile_id' => $target->id,
                    'user_id' => $parent->id,
                ],
                [
                    'relationship' => $parent->pivot->relationship,
                    'updated_at' => $timestamp,
                    'created_at' => $timestamp,
                ]
            );
        }
    }

    private function moveVaccinations(ChildProfile $target, ChildProfile $source): void
    {
        $records = $source->vaccinations()->with(['child.barangay', 'child.creator', 'vaccineType', 'recorder', 'submitter', 'verifier'])->get();

        foreach ($records as $record) {
            $record->update(['child_profile_id' => $target->id]);

            $this->offlineSync->queueUpsert(
                $record->fresh(['child.barangay', 'child.creator', 'vaccineType', 'recorder', 'submitter', 'verifier'])
            );
        }
    }

    private function moveAdverseEventReports(ChildProfile $target, ChildProfile $source): void
    {
        $reports = $source->adverseEventReports()->with(['child.barangay', 'vaccinationRecord', 'vaccineType', 'reporter'])->get();

        foreach ($reports as $report) {
            $report->update(['child_profile_id' => $target->id]);

            $this->offlineSync->queueUpsert(
                $report->fresh(['child.barangay', 'vaccinationRecord', 'vaccineType', 'reporter'])
            );
        }
    }

    private function moveReminders(ChildProfile $target, ChildProfile $source): void
    {
        $reminders = VaccinationReminder::query()
            ->where('child_profile_id', $source->id)
            ->get();

        foreach ($reminders as $reminder) {
            $conflict = VaccinationReminder::query()
                ->where('child_profile_id', $target->id)
                ->where('parent_id', $reminder->parent_id)
                ->where('vaccine_name', $reminder->vaccine_name)
                ->where('dose_number', $reminder->dose_number)
                ->whereDate('due_at', $reminder->due_at)
                ->where('channel', $reminder->channel)
                ->exists();

            if ($conflict) {
                $reminder->delete();

                continue;
            }

            $reminder->update(['child_profile_id' => $target->id]);
        }
    }

    private function moveSeriesVersions(ChildProfile $target, ChildProfile $source): void
    {
        $seriesVersions = $source->seriesVersions()->get();

        foreach ($seriesVersions as $seriesVersion) {
            $existing = ChildVaccineSeriesVersion::query()
                ->where('child_profile_id', $target->id)
                ->where('vaccine_type_id', $seriesVersion->vaccine_type_id)
                ->first();

            if ($existing === null) {
                $seriesVersion->update(['child_profile_id' => $target->id]);

                continue;
            }

            $existingAssignedAt = $existing->assigned_at?->getTimestamp() ?? PHP_INT_MIN;
            $sourceAssignedAt = $seriesVersion->assigned_at?->getTimestamp() ?? PHP_INT_MIN;

            if ($sourceAssignedAt > $existingAssignedAt) {
                $existing->update([
                    'vaccine_schedule_version_id' => $seriesVersion->vaccine_schedule_version_id,
                    'assigned_at' => $seriesVersion->assigned_at,
                    'assignment_reason' => $seriesVersion->assignment_reason,
                ]);
            }

            $seriesVersion->delete();
        }
    }
}
