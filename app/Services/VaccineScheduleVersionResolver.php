<?php

namespace App\Services;

use App\Models\ChildProfile;
use App\Models\ChildVaccineSeriesVersion;
use App\Models\VaccineSchedule;
use App\Models\VaccineScheduleVersion;
use App\Models\VaccineType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class VaccineScheduleVersionResolver
{
    public function activeVersion(): ?VaccineScheduleVersion
    {
        return VaccineScheduleVersion::query()
            ->where('status', 'active')
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();
    }

    public function versionForChildAndVaccine(
        ChildProfile $child,
        VaccineType $vaccineType,
        Collection $seriesRecords,
        ?VaccineScheduleVersion $fallbackVersion = null,
    ): ?VaccineScheduleVersion {
        $child->loadMissing('seriesVersions.scheduleVersion');

        $assignment = $child->seriesVersions
            ->firstWhere('vaccine_type_id', $vaccineType->id);

        if ($assignment?->scheduleVersion !== null) {
            return $assignment->scheduleVersion;
        }

        if ($seriesRecords->isNotEmpty()) {
            $referenceDate = Carbon::parse(
                $seriesRecords->sortBy('administered_at')->first()->administered_at
            )->startOfDay();

            $version = $this->resolveByDate($referenceDate) ?? $this->activeVersion();

            if ($version !== null) {
                ChildVaccineSeriesVersion::updateOrCreate(
                    [
                        'child_profile_id' => $child->id,
                        'vaccine_type_id' => $vaccineType->id,
                    ],
                    [
                        'vaccine_schedule_version_id' => $version->id,
                        'assigned_at' => now(),
                        'assignment_reason' => 'resolved_from_existing_series',
                    ],
                );

                $child->unsetRelation('seriesVersions');
                $child->load('seriesVersions.scheduleVersion');
            }

            return $version;
        }

        return $fallbackVersion ?? $this->activeVersion();
    }

    /**
     * @return Collection<string, Collection<int, VaccineSchedule>>
     */
    public function scheduleRowsForChild(ChildProfile $child, ?VaccineScheduleVersion $fallbackVersion = null): Collection
    {
        $child->loadMissing(['vaccinations.vaccineType', 'seriesVersions.scheduleVersion']);

        $recordsByVaccineType = $child->vaccinations
            ->filter(fn ($record) => $record->vaccineType !== null && $record->verification_status !== 'rejected')
            ->groupBy('vaccine_type_id');

        $vaccineTypes = VaccineType::query()
            ->where(function ($query) use ($recordsByVaccineType) {
                $query->where('active', true);

                if ($recordsByVaccineType->isNotEmpty()) {
                    $query->orWhereIn('id', $recordsByVaccineType->keys()->all());
                }
            })
            ->orderBy('name')
            ->get();

        $versionByVaccineType = [];

        foreach ($vaccineTypes as $vaccineType) {
            $version = $this->versionForChildAndVaccine(
                $child,
                $vaccineType,
                $recordsByVaccineType->get($vaccineType->id, collect()),
                $fallbackVersion,
            );

            if ($version !== null) {
                $versionByVaccineType[$vaccineType->id] = $version->id;
            }
        }

        if ($versionByVaccineType === []) {
            return collect();
        }

        return VaccineSchedule::query()
            ->where('active', true)
            ->whereIn('vaccine_schedule_version_id', array_values(array_unique($versionByVaccineType)))
            ->with(['vaccineType', 'scheduleVersion'])
            ->orderBy('vaccine_type_id')
            ->orderBy('dose_number')
            ->get()
            ->filter(function (VaccineSchedule $schedule) use ($versionByVaccineType) {
                return ($versionByVaccineType[$schedule->vaccine_type_id] ?? null) === $schedule->vaccine_schedule_version_id
                    && $schedule->vaccineType !== null;
            })
            ->groupBy(fn (VaccineSchedule $schedule) => $schedule->vaccineType->code);
    }

    private function resolveByDate(Carbon $referenceDate): ?VaccineScheduleVersion
    {
        return VaccineScheduleVersion::query()
            ->whereIn('status', ['active', 'archived'])
            ->whereDate('effective_date', '<=', $referenceDate->toDateString())
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();
    }
}
