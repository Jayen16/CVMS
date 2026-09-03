<?php

namespace App\Services;

use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PredictiveAnalyticsService
{
    public function __construct(
        private readonly VaccineScheduleVersionResolver $scheduleVersions,
        private readonly ImmunizationSuggestionService $suggestions,
    ) {}

    /**
     * Estimate demand using scheduled missing doses and the recent monthly administration average.
     * This is an advisory estimate, not an automated procurement instruction.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function vaccineDemand(User $user, int $months = 3): Collection
    {
        $months = max(1, min(12, $months));
        $today = Carbon::today();
        $horizonEnd = $today->copy()->addMonthsNoOverflow($months);
        $children = ChildProfile::query()->visibleTo($user)->with(['vaccinations.vaccineType'])->get();
        $scheduled = collect();

        foreach ($children as $child) {
            $records = $child->vaccinations
                ->filter(fn (VaccinationRecord $record) => $record->verification_status !== 'rejected')
                ->groupBy('vaccine_type_id');

            foreach ($this->scheduleVersions->scheduleRowsForChild($child) as $doses) {
                foreach ($doses as $dose) {
                    $recorded = $records->get($dose->vaccine_type_id, collect())
                        ->contains(fn (VaccinationRecord $record) => (int) $record->dose_number === (int) $dose->dose_number);
                    $dueAt = $dose->dueDateFromBirthdate(Carbon::parse($child->birthdate));

                    if (! $recorded && $dueAt->betweenIncluded($today, $horizonEnd)) {
                        $scheduled[$dose->vaccine_type_id] = ($scheduled[$dose->vaccine_type_id] ?? 0) + 1;
                    }
                }
            }
        }

        $historyStart = $today->copy()->subMonthsNoOverflow(3);
        $historical = VaccinationRecord::query()
            ->whereBetween('administered_at', [$historyStart->toDateString(), $today->toDateString()])
            ->where('verification_status', '!=', 'rejected')
            ->whereHas('child', fn ($query) => $query->whereIn('barangay_id', $user->accessibleBarangayIds()))
            ->selectRaw('vaccine_type_id, count(*) as total')
            ->groupBy('vaccine_type_id')
            ->pluck('total', 'vaccine_type_id');

        return $children->flatMap(fn (ChildProfile $child) => $child->vaccinations->map(fn (VaccinationRecord $record) => $record->vaccineType))
            ->merge($scheduled->keys()->map(fn ($id) => \App\Models\VaccineType::find($id)))
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(function ($vaccine) use ($scheduled, $historical, $months, $horizonEnd): array {
                $recent = (int) ($historical[$vaccine->id] ?? 0);
                $average = round($recent / 3, 1);
                $scheduledDue = (int) ($scheduled[$vaccine->id] ?? 0);

                return [
                    'vaccine' => $vaccine,
                    'forecast_months' => $months,
                    'horizon_end' => $horizonEnd,
                    'scheduled_due' => $scheduledDue,
                    'recent_three_months' => $recent,
                    'monthly_average' => $average,
                    'estimated_demand' => max($scheduledDue, (int) ceil($average * $months)),
                ];
            });
    }

    /**
     * Produce transparent, rule-based missed-dose risk scores for children with an actionable dose.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function missedDoseRisk(User $user): Collection
    {
        $today = Carbon::today();

        return ChildProfile::query()
            ->visibleTo($user)
            ->with(['barangay', 'parents', 'vaccinations'])
            ->get()
            ->map(function (ChildProfile $child) use ($today): ?array {
                $suggestion = $this->suggestions->suggestNextDose($child);

                if ($suggestion['due_at'] === null || $suggestion['status'] === 'complete') {
                    return null;
                }

                $score = match ($suggestion['status']) {
                    'overdue' => min(60, 35 + (int) $suggestion['due_at']->diffInDays($today)),
                    'delayed' => 30,
                    'due' => 20,
                    default => 0,
                };
                $reasons = [];

                if (in_array($suggestion['status'], ['overdue', 'delayed', 'due'], true)) {
                    $reasons[] = ucfirst($suggestion['status']).' scheduled dose';
                }
                if ($child->parents->every(fn (User $parent) => blank($parent->phone) && blank($parent->email)) && blank($child->guardian_contact)) {
                    $score += 15;
                    $reasons[] = 'No guardian contact method';
                }
                if ($child->vaccinations->contains(fn (VaccinationRecord $record) => $record->verification_status === 'pending')) {
                    $score += 10;
                    $reasons[] = 'Vaccination submission pending verification';
                }

                $score = min(95, $score);

                return [
                    'child' => $child,
                    'suggestion' => $suggestion,
                    'score' => $score,
                    'risk_level' => $score >= 60 ? 'high' : ($score >= 30 ? 'medium' : 'low'),
                    'reasons' => $reasons,
                ];
            })
            ->filter()
            ->sortByDesc('score')
            ->values();
    }
}
