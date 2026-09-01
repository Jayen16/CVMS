<?php

namespace App\Livewire;

use App\Models\ChildProfile;
use App\Models\VaccinationRecord;
use App\Models\VaccineSchedule;
use App\Models\VaccineType;
use App\Services\VaccineScheduleVersionResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

class ChildTimelinePage extends Component
{
    public ChildProfile $child;

    public function mount(ChildProfile $child): void
    {
        $this->child = $child;
    }

    public function render(): View
    {
        abort_unless(auth()->user()->canViewChildrenRegistry(), 403);
        $this->authorizeChild($this->child);

        $this->child->load(['barangay', 'vaccinations.vaccineType']);
        $selectedVaccine = request()->string('vaccine')->toString();

        return view('children.timeline', [
            'child' => $this->child,
            'timeline' => $this->timeline($this->child, $selectedVaccine, app(VaccineScheduleVersionResolver::class)),
            'selectedVaccine' => $selectedVaccine,
            'vaccines' => VaccineType::where('active', true)->orderBy('name')->get(),
            'indications' => VaccineSchedule::indicationOptions(),
            'source' => config('immunization.source'),
            'sourceUrl' => config('immunization.source_url'),
        ])->layout('layouts.app', [
            'title' => $this->child->full_name.' timeline',
        ]);
    }

    private function authorizeChild(ChildProfile $child): void
    {
        abort_if(auth()->user()->isMunicipalAdmin() && ! auth()->user()->canAccessBarangay($child->barangay_id), 403);
        abort_if(auth()->user()->isNurse() && $child->barangay_id !== auth()->user()->barangay_id, 403);
        abort_if(auth()->user()->isBarangayAdmin() && $child->barangay_id !== auth()->user()->barangay_id, 403);
        abort_if(auth()->user()->isParent() && ! $child->parents()->whereKey(auth()->id())->exists(), 403);
    }

    /**
     * @return list<array{name: string, code: string, doses: list<array<string, mixed>>, records: list<VaccinationRecord>}>
     */
    private function timeline(ChildProfile $child, string $selectedVaccine, VaccineScheduleVersionResolver $versions): array
    {
        $scheduleRows = $versions->scheduleRowsForChild($child);
        $rows = [];

        foreach ($scheduleRows as $code => $doses) {
            if (! is_string($code) || $code === '') {
                continue;
            }

            if ($selectedVaccine !== '' && $selectedVaccine !== $code) {
                continue;
            }

            $vaccine = $doses->first()->vaccineType;
            $records = $child->vaccinations
                ->filter(fn (VaccinationRecord $record) => $record->vaccineType?->code === $code)
                ->sortBy('administered_at')
                ->values();

            $rows[] = [
                'name' => $vaccine->name,
                'code' => $code,
                'version_name' => $doses->first()->scheduleVersion?->name,
                'indication_label' => $doses->first()->indicationLabel(),
                'indication_class' => $doses->first()->indicationClass(),
                'doses' => $this->dosePoints($child, $doses->values(), $records),
                'records' => array_values($records->all()),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, VaccineSchedule>  $doses
     * @param  Collection<int, VaccinationRecord>  $records
     * @return list<array<string, mixed>>
     */
    private function dosePoints(ChildProfile $child, Collection $doses, Collection $records): array
    {
        $birthdate = Carbon::parse($child->birthdate)->startOfDay();
        $points = [];

        foreach ($doses as $dose) {
            $doseNumber = (int) $dose->dose_number;
            $record = $records->first(fn (VaccinationRecord $entry) => (int) $entry->dose_number === $doseNumber);
            $dueAt = $dose->dueDateFromBirthdate($birthdate);

            $points[] = [
                'dose' => $doseNumber,
                'label' => $dose->label,
                'age_summary' => $dose->ageSummary(),
                'indication_label' => $dose->indicationLabel(),
                'indication_class' => $dose->indicationClass(),
                'due_at' => $dueAt,
                'action_at' => $this->actionDateFor($record, $dueAt),
                'position' => $this->positionForDueDate($birthdate, $dueAt),
                'record' => $record,
                'status' => $this->statusFor($record, $dueAt),
            ];
        }

        return $points;
    }

    private function positionForDueDate(Carbon $birthdate, Carbon $dueAt): int
    {
        $timelineEndsAt = $birthdate->copy()->addYearsNoOverflow(18);
        $elapsedDays = max(0, (int) $birthdate->diffInDays($dueAt, false));
        $totalDays = max(1, (int) $birthdate->diffInDays($timelineEndsAt));

        return min(100, max(0, (int) round(($elapsedDays / $totalDays) * 100)));
    }

    private function actionDateFor(?VaccinationRecord $record, Carbon $dueAt): ?Carbon
    {
        if ($record !== null) {
            return null;
        }

        return $dueAt->isPast() ? Carbon::today() : $dueAt;
    }

    private function statusFor(?VaccinationRecord $record, Carbon $dueAt): string
    {
        if ($record !== null) {
            return $record->verification_status === 'pending' ? 'pending' : 'given';
        }

        return $dueAt->isPast() ? 'overdue' : 'upcoming';
    }
}
