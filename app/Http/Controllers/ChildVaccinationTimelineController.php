<?php

namespace App\Http\Controllers;

use App\Models\ChildProfile;
use App\Models\VaccinationRecord;
use App\Models\VaccineSchedule;
use App\Models\VaccineType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ChildVaccinationTimelineController extends Controller
{
    public function __invoke(Request $request, ChildProfile $child): View
    {
        abort_unless(auth()->user()->canViewChildrenRegistry(), 403);
        $this->authorizeChild($child);

        $child->load(['barangay', 'vaccinations.vaccineType']);
        $selectedVaccine = $request->string('vaccine')->toString();
        $timeline = $this->timeline($child, $selectedVaccine);

        return view('children.timeline', [
            'child' => $child,
            'timeline' => $timeline,
            'selectedVaccine' => $selectedVaccine,
            'vaccines' => VaccineType::where('active', true)->orderBy('name')->get(),
            'indications' => VaccineSchedule::indicationOptions(),
            'source' => config('immunization.source'),
            'sourceUrl' => config('immunization.source_url'),
        ]);
    }

    private function authorizeChild(ChildProfile $child): void
    {
        abort_if(auth()->user()->isNurse() && $child->barangay_id !== auth()->user()->barangay_id, 403);
        abort_if(auth()->user()->isBarangayAdmin() && $child->barangay_id !== auth()->user()->barangay_id, 403);
        abort_if(
            auth()->user()->isParent() && ! $child->parents()->whereKey(auth()->id())->exists(),
            403
        );
    }

    /**
     * @return list<array{name: string, code: string, doses: list<array<string, mixed>>, records: list<VaccinationRecord>}>
     */
    private function timeline(ChildProfile $child, string $selectedVaccine): array
    {
        $scheduleRows = VaccineSchedule::query()
            ->where('active', true)
            ->with('vaccineType')
            ->orderBy('vaccine_type_id')
            ->orderBy('dose_number')
            ->get()
            ->groupBy(fn (VaccineSchedule $schedule) => $schedule->vaccineType->code);
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
            $record = $records->first(fn (VaccinationRecord $record) => (int) $record->dose_number === $doseNumber);
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
        $months = max(0, (int) $birthdate->diffInMonths($dueAt));

        return min(100, max(0, (int) round(($months / 60) * 100)));
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
