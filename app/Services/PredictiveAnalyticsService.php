<?php

namespace App\Services;

use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PredictiveAnalyticsService
{
    public function __construct(
        private readonly VaccineScheduleVersionResolver $scheduleVersions,
        private readonly ImmunizationSuggestionService $suggestions,
    ) {}

    /**
     * Forecast demand from longitudinal vaccine use, scheduled doses, and catch-up backlog.
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
        $backlog = collect();

        foreach ($children as $child) {
            $records = $child->vaccinations
                ->filter(fn (VaccinationRecord $record) => $record->verification_status !== 'rejected')
                ->groupBy('vaccine_type_id');

            foreach ($this->scheduleVersions->scheduleRowsForChild($child) as $doses) {
                foreach ($doses as $dose) {
                    $recorded = $records->get($dose->vaccine_type_id, collect())
                        ->contains(fn (VaccinationRecord $record) => (int) $record->dose_number === (int) $dose->dose_number);
                    $dueAt = $dose->dueDateFromBirthdate(Carbon::parse($child->birthdate));

                    if ($recorded) {
                        continue;
                    }
                    if ($dueAt->betweenIncluded($today, $horizonEnd)) {
                        $scheduled[$dose->vaccine_type_id] = ($scheduled[$dose->vaccine_type_id] ?? 0) + 1;
                    } elseif ($dueAt->isBefore($today)) {
                        $backlog[$dose->vaccine_type_id] = ($backlog[$dose->vaccine_type_id] ?? 0) + 1;
                    }
                }
            }
        }

        $historyStart = $today->copy()->subMonthsNoOverflow(12);
        $recentStart = $today->copy()->subMonthsNoOverflow(3);
        $priorStart = $today->copy()->subMonthsNoOverflow(6);
        $scope = fn () => VaccinationRecord::query()
            ->where('verification_status', '!=', 'rejected')
            ->whereHas('child', fn ($query) => $query->whereIn('barangay_id', $user->accessibleBarangayIds()));
        $history = $scope()->whereBetween('administered_at', [$historyStart->toDateString(), $today->toDateString()])
            ->selectRaw('vaccine_type_id, count(*) as total')->groupBy('vaccine_type_id')->pluck('total', 'vaccine_type_id');
        $recent = $scope()->whereBetween('administered_at', [$recentStart->toDateString(), $today->toDateString()])
            ->selectRaw('vaccine_type_id, count(*) as total')->groupBy('vaccine_type_id')->pluck('total', 'vaccine_type_id');
        $prior = $scope()->whereBetween('administered_at', [$priorStart->toDateString(), $recentStart->copy()->subDay()->toDateString()])
            ->selectRaw('vaccine_type_id, count(*) as total')->groupBy('vaccine_type_id')->pluck('total', 'vaccine_type_id');

        return $children->flatMap(fn (ChildProfile $child) => $child->vaccinations->map(fn (VaccinationRecord $record) => $record->vaccineType))
            ->merge($scheduled->keys()->merge($backlog->keys())->unique()->map(fn ($id) => VaccineType::find($id)))
            ->filter()->unique('id')->sortBy('name')->values()
            ->map(function (VaccineType $vaccine) use ($scheduled, $backlog, $history, $recent, $prior, $months, $horizonEnd): array {
                $last12 = (int) ($history[$vaccine->id] ?? 0);
                $last3 = (int) ($recent[$vaccine->id] ?? 0);
                $prior3 = (int) ($prior[$vaccine->id] ?? 0);
                $recentAverage = round($last3 / 3, 1);
                $historicalAverage = round($last12 / 12, 1);
                $trendAdjustment = max(0, (int) ceil(($recentAverage - round($prior3 / 3, 1)) * $months));
                $scheduledDue = (int) ($scheduled[$vaccine->id] ?? 0);
                $catchUpBacklog = (int) ($backlog[$vaccine->id] ?? 0);
                $historicalBaseline = (int) ceil(max($recentAverage, $historicalAverage) * $months);

                return [
                    'vaccine' => $vaccine,
                    'forecast_months' => $months,
                    'horizon_end' => $horizonEnd,
                    'scheduled_due' => $scheduledDue,
                    'catch_up_backlog' => $catchUpBacklog,
                    'recent_three_months' => $last3,
                    'prior_three_months' => $prior3,
                    'historical_monthly_average' => $historicalAverage,
                    'trend_adjustment' => $trendAdjustment,
                    'estimated_demand' => max($scheduledDue + $catchUpBacklog, $historicalBaseline) + $trendAdjustment,
                ];
            });
    }

    /**
     * Estimate missed-dose risk from historical child behavior and current access signals.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function missedDoseRisk(User $user): Collection
    {
        $today = Carbon::today();
        $children = ChildProfile::query()->visibleTo($user)->with(['barangay', 'parents', 'vaccinations'])->get();
        $allOpportunities = 0;
        $allMissedOrDelayed = 0;

        foreach ($children as $child) {
            $stats = $this->doseOutcomeStats($child, $today);
            $allOpportunities += $stats['opportunities'];
            $allMissedOrDelayed += $stats['missed_or_delayed'];
        }

        // Avoid overfitting a tiny local dataset; use a conservative prior until 10 observations exist.
        $baselineRate = $allOpportunities >= 10 ? ($allMissedOrDelayed / $allOpportunities) * 100 : 10.0;

        return $children->map(function (ChildProfile $child) use ($today, $baselineRate): ?array {
            $suggestion = $this->suggestions->suggestNextDose($child);

            if ($suggestion['due_at'] === null || $suggestion['status'] === 'complete') {
                return null;
            }

            $prior = $this->doseOutcomeStats($child, $today, $suggestion['due_at']);
            $priorRate = $prior['opportunities'] > 0
                ? ($prior['missed_or_delayed'] / $prior['opportunities']) * 100
                : $baselineRate;
            $historyWeight = min(1, $prior['opportunities'] / 3);
            $probability = ($priorRate * $historyWeight) + ($baselineRate * (1 - $historyWeight));
            $reasons = [];

            if ($prior['opportunities'] > 0) {
                $reasons[] = "{$prior['missed_or_delayed']} of {$prior['opportunities']} prior doses missed or delayed";
            } else {
                $reasons[] = 'No prior dose outcome history; population baseline used';
            }
            if ($child->parents->every(fn (User $parent) => blank($parent->phone) && blank($parent->email)) && blank($child->guardian_contact)) {
                $probability += 10;
                $reasons[] = 'No guardian contact method';
            }
            if ($child->vaccinations->contains(fn (VaccinationRecord $record) => $record->verification_status === 'pending')) {
                $probability += 5;
                $reasons[] = 'Vaccination submission pending verification';
            }
            if (in_array($suggestion['status'], ['overdue', 'delayed', 'due'], true)) {
                $probability += match ($suggestion['status']) {
                    'overdue' => min(25, 10 + (int) $suggestion['due_at']->diffInDays($today)),
                    'delayed' => 15,
                    default => 8,
                };
                $reasons[] = 'Current schedule requires follow-up';
            }

            $probability = (int) min(95, max(1, round($probability)));

            return [
                'child' => $child,
                'suggestion' => $suggestion,
                'risk_probability' => $probability,
                'score' => $probability,
                'risk_level' => $probability >= 60 ? 'high' : ($probability >= 30 ? 'medium' : 'low'),
                'features' => [
                    'prior_opportunities' => $prior['opportunities'],
                    'prior_missed_or_delayed' => $prior['missed_or_delayed'],
                    'baseline_rate' => round($baselineRate, 1),
                ],
                'reasons' => $reasons,
            ];
        })->filter()->sortByDesc('risk_probability')->values();
    }

    /** @return array{opportunities: int, missed_or_delayed: int} */
    private function doseOutcomeStats(ChildProfile $child, Carbon $today, ?Carbon $beforeDue = null): array
    {
        $opportunities = 0;
        $missedOrDelayed = 0;
        $records = $child->vaccinations->filter(fn (VaccinationRecord $record) => $record->verification_status !== 'rejected');
        $threshold = max(1, (int) config('immunization.overdue_threshold_days', 7));

        foreach ($this->scheduleVersions->scheduleRowsForChild($child) as $doses) {
            foreach ($doses as $dose) {
                $dueAt = $dose->dueDateFromBirthdate(Carbon::parse($child->birthdate));
                if (! $dueAt->isBefore($today) || ($beforeDue !== null && ! $dueAt->isBefore($beforeDue))) {
                    continue;
                }

                $opportunities++;
                $record = $records->first(fn (VaccinationRecord $entry) => $entry->vaccine_type_id === $dose->vaccine_type_id && (int) $entry->dose_number === (int) $dose->dose_number);
                if ($record === null || Carbon::parse($record->administered_at)->greaterThan($dueAt->copy()->addDays($threshold))) {
                    $missedOrDelayed++;
                }
            }
        }

        return [
            'opportunities' => $opportunities,
            'missed_or_delayed' => $missedOrDelayed,
        ];
    }
}
