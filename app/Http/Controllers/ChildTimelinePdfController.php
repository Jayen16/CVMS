<?php

namespace App\Http\Controllers;

use App\Models\ChildProfile;
use App\Models\VaccinationRecord;
use App\Models\VaccineSchedule;
use App\Models\VaccineType;
use App\Services\VaccineScheduleVersionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;

class ChildTimelinePdfController extends Controller
{
    public function __invoke(Request $request, ChildProfile $child)
    {
        abort_unless(auth()->user()->canViewChildrenRegistry(), 403);
        $this->authorizeChild($child);

        $child->load(['barangay', 'vaccinations.vaccineType']);
        $selectedVaccine = $request->string('vaccine')->toString();
        $timeline = $this->timeline($child, $selectedVaccine, app(VaccineScheduleVersionResolver::class));

        return Pdf::view('children.timeline-pdf', [
            'child' => $child,
            'timeline' => $timeline,
            'selectedVaccine' => $selectedVaccine,
            'vaccines' => VaccineType::where('active', true)->orderBy('name')->get(),
            'generatedAt' => now(),
        ])
            ->format('a4')
            ->landscape()
            ->margins(8, 8, 8, 8)
            ->name('vaccination-timeline-'.$child->id.'.pdf');
    }

    private function authorizeChild(ChildProfile $child): void
    {
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
                'due_at' => $dueAt,
                'action_at' => $record === null ? ($dueAt->isPast() ? Carbon::today() : $dueAt) : null,
                'record' => $record,
                'status' => $record !== null
                    ? ($record->verification_status === 'pending' ? 'pending' : 'given')
                    : ($dueAt->isPast() ? 'overdue' : 'upcoming'),
            ];
        }

        return $points;
    }
}
