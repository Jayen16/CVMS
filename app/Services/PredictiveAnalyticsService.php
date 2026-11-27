<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineInventoryItem;
use App\Models\VaccineScheduleVersion;
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
    public function vaccineDemand(
        User $user,
        int $months = 3,
        ?VaccineScheduleVersion $scheduleVersion = null,
        string $regionId = 'all',
        string $provinceId = 'all',
        string $municipalityId = 'all',
        string $barangayId = 'all',
    ): Collection {
        return $this->scheduleVersions->withCachedSchedules(function () use ($user, $months, $scheduleVersion, $regionId, $provinceId, $municipalityId, $barangayId) {
            $months = max(1, min(12, $months));
            $today = Carbon::today();
            $horizonEnd = $today->copy()->addMonthsNoOverflow($months);
            $barangayIds = $this->filteredBarangayIds($user, $regionId, $provinceId, $municipalityId, $barangayId);
            $children = ChildProfile::query()->visibleTo($user)
                ->whereIn('barangay_id', $barangayIds)
                ->with(['vaccinations.vaccineType', 'seriesVersions.scheduleVersion'])->lazyById(25);
            $scheduled = collect();
            $backlog = collect();
            $usedVaccineIds = collect();

            foreach ($children as $child) {
                foreach ($child->vaccinations as $record) {
                    $usedVaccineIds->put($record->vaccine_type_id, true);
                }
                $records = $child->vaccinations
                    ->filter(fn (VaccinationRecord $record) => $record->verification_status !== 'rejected')
                    ->groupBy('vaccine_type_id');

                foreach ($this->scheduleVersions->scheduleRowsForChild($child, $scheduleVersion) as $doses) {
                    foreach ($doses as $dose) {
                        $recorded = $records->get($dose->vaccine_type_id, collect())
                            ->contains(fn (VaccinationRecord $record) => (int) $record->dose_number === (int) $dose->dose_number);
                        if ($recorded) {
                            continue;
                        }
                        $dueAt = $dose->dueDateFromBirthdate(Carbon::parse($child->birthdate));
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
                ->whereHas('child', fn ($query) => $query->whereIn('barangay_id', $barangayIds));
            $history = $scope()->whereBetween('administered_at', [$historyStart->toDateString(), $today->toDateString()])
                ->selectRaw('vaccine_type_id, count(*) as total')->groupBy('vaccine_type_id')->pluck('total', 'vaccine_type_id');
            $recent = $scope()->whereBetween('administered_at', [$recentStart->toDateString(), $today->toDateString()])
                ->selectRaw('vaccine_type_id, count(*) as total')->groupBy('vaccine_type_id')->pluck('total', 'vaccine_type_id');
            $prior = $scope()->whereBetween('administered_at', [$priorStart->toDateString(), $recentStart->copy()->subDay()->toDateString()])
                ->selectRaw('vaccine_type_id, count(*) as total')->groupBy('vaccine_type_id')->pluck('total', 'vaccine_type_id');
            $inventory = VaccineInventoryItem::query()
                ->whereIn('barangay_id', $barangayIds)
                ->withSum(['transactions as stock_in' => fn ($query) => $query->where('movement', 'in')], 'quantity')
                ->withSum(['transactions as stock_out' => fn ($query) => $query->where('movement', 'out')], 'quantity')
                ->get()->groupBy('vaccine_type_id')->map(fn ($items) => (int) $items->sum(fn ($item) => ($item->stock_in ?? 0) - ($item->stock_out ?? 0)));

            return VaccineType::query()->whereIn('id', $usedVaccineIds->keys()->merge($scheduled->keys())->merge($backlog->keys())->unique())
                ->orderBy('name')->get()
                ->map(function (VaccineType $vaccine) use ($scheduled, $backlog, $history, $recent, $prior, $inventory, $months, $horizonEnd): array {
                    $last12 = (int) ($history[$vaccine->id] ?? 0);
                    $last3 = (int) ($recent[$vaccine->id] ?? 0);
                    $prior3 = (int) ($prior[$vaccine->id] ?? 0);
                    $recentAverage = round($last3 / 3, 1);
                    $historicalAverage = round($last12 / 12, 1);
                    $trendAdjustment = max(0, (int) ceil(($recentAverage - round($prior3 / 3, 1)) * $months));
                    $scheduledDue = (int) ($scheduled[$vaccine->id] ?? 0);
                    $catchUpBacklog = (int) ($backlog[$vaccine->id] ?? 0);
                    $historicalBaseline = (int) ceil(max($recentAverage, $historicalAverage) * $months);
                    $estimatedDemand = max($scheduledDue + $catchUpBacklog, $historicalBaseline) + $trendAdjustment;
                    $availableStock = (int) ($inventory[$vaccine->id] ?? 0);

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
                        'estimated_demand' => $estimatedDemand,
                        'available_stock' => $availableStock,
                        'projected_balance' => $availableStock - $estimatedDemand,
                        'stock_status' => $availableStock >= $estimatedDemand ? 'sufficient' : 'shortage',
                    ];
                });
        });
    }

    /** @return Collection<int, string> */
    private function filteredBarangayIds(User $user, string $regionId, string $provinceId, string $municipalityId, string $barangayId): Collection
    {
        $query = Barangay::query()->whereIn('id', $user->accessibleBarangayIds());

        if ($regionId !== 'all') {
            $query->whereHas('municipalityRelation.province', fn ($location) => $location->where('region_id', $regionId));
        }
        if ($provinceId !== 'all') {
            $query->whereHas('municipalityRelation', fn ($location) => $location->where('province_id', $provinceId));
        }
        if ($municipalityId !== 'all') {
            $query->where('municipality_id', $municipalityId);
        }
        if ($barangayId !== 'all') {
            $query->whereKey($barangayId);
        }

        return $query->pluck('id');
    }

    /**
     * Estimate missed-dose risk from historical child behavior and current access signals.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function missedDoseRisk(
        User $user,
        string $regionId = 'all',
        string $provinceId = 'all',
        string $municipalityId = 'all',
        string $barangayId = 'all',
    ): Collection {
        return $this->suggestions->withCachedSchedules(fn () => $this->scheduleVersions->withCachedSchedules(function () use ($user, $regionId, $provinceId, $municipalityId, $barangayId) {
            $today = Carbon::today();
            $barangayIds = $this->filteredBarangayIds($user, $regionId, $provinceId, $municipalityId, $barangayId);
            $children = ChildProfile::query()->visibleTo($user)
                ->whereIn('barangay_id', $barangayIds)
                ->with(['barangay', 'parents', 'vaccinations.vaccineType', 'seriesVersions.scheduleVersion'])
                ->withCount('vaccinations')->lazyById(25);
            $allOpportunities = 0;
            $allMissedOrDelayed = 0;
            $prepared = collect();

            foreach ($children as $child) {
                $suggestion = $this->suggestions->suggestNextDose($child);
                $stats = $this->doseOutcomeStats($child, $today, $suggestion['due_at']);
                $allOpportunities += $stats['opportunities'];
                $allMissedOrDelayed += $stats['missed_or_delayed'];
                if ($suggestion['due_at'] !== null && $suggestion['status'] !== 'complete') {
                    $prepared->push([
                        'child' => $child,
                        'suggestion' => $suggestion,
                        'prior' => ['opportunities' => $stats['prior_opportunities'], 'missed_or_delayed' => $stats['prior_missed_or_delayed']],
                        'pending' => $child->vaccinations->contains('verification_status', 'pending'),
                        'no_contact' => $child->parents->every(fn (User $parent) => blank($parent->phone) && blank($parent->email)) && blank($child->guardian_contact),
                    ]);
                }
                // Keep only the display data after calculating this child's history.
                $child->unsetRelation('vaccinations');
                $child->unsetRelation('seriesVersions');
                $child->unsetRelation('parents');
                $child->unsetRelation('barangay');
            }

            // Avoid overfitting a tiny local dataset; use a conservative prior until 10 observations exist.
            $baselineRate = $allOpportunities >= 10 ? ($allMissedOrDelayed / $allOpportunities) * 100 : 10.0;

            return $prepared->map(function (array $entry) use ($today, $baselineRate): array {
                $child = $entry['child'];
                $suggestion = $entry['suggestion'];
                $prior = $entry['prior'];
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
                if ($entry['no_contact']) {
                    $probability += 10;
                    $reasons[] = 'No guardian contact method';
                }
                if ($entry['pending']) {
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
        }));
    }

    /** @return array{opportunities: int, missed_or_delayed: int, prior_opportunities: int, prior_missed_or_delayed: int} */
    private function doseOutcomeStats(ChildProfile $child, Carbon $today, ?Carbon $beforeDue = null): array
    {
        $opportunities = 0;
        $missedOrDelayed = 0;
        $priorOpportunities = 0;
        $priorMissedOrDelayed = 0;
        $records = $child->vaccinations->filter(fn (VaccinationRecord $record) => $record->verification_status !== 'rejected')
            ->reverse()
            ->keyBy(fn (VaccinationRecord $record) => $record->vaccine_type_id.':'.$record->dose_number);
        $threshold = max(1, (int) config('immunization.overdue_threshold_days', 7));
        $birthdate = Carbon::parse($child->birthdate);

        foreach ($this->scheduleVersions->scheduleRowsForChild($child) as $doses) {
            foreach ($doses as $dose) {
                $dueAt = $dose->dueDateFromBirthdate($birthdate);
                if (! $dueAt->isBefore($today)) {
                    continue;
                }

                $opportunities++;
                $isPrior = $beforeDue === null || $dueAt->isBefore($beforeDue);
                if ($isPrior) {
                    $priorOpportunities++;
                }
                $record = $records->get($dose->vaccine_type_id.':'.$dose->dose_number);
                if ($record === null || Carbon::parse($record->administered_at)->greaterThan($dueAt->copy()->addDays($threshold))) {
                    $missedOrDelayed++;
                    if ($isPrior) {
                        $priorMissedOrDelayed++;
                    }
                }
            }
        }

        return [
            'opportunities' => $opportunities,
            'missed_or_delayed' => $missedOrDelayed,
            'prior_opportunities' => $priorOpportunities,
            'prior_missed_or_delayed' => $priorMissedOrDelayed,
        ];
    }
}
