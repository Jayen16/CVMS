<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccineScheduleVersion;
use App\Services\ImmunizationSuggestionService;
use App\Services\PredictiveAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;

class PlanningReportController extends Controller
{
    public function forecast(Request $request, PredictiveAnalyticsService $analytics)
    {
        $user = auth()->user();
        abort_unless($user->canViewDefaulters(), 403);
        $months = in_array((int) $request->input('months', 3), [1, 3, 6, 12], true) ? (int) $request->input('months', 3) : 3;
        $version = VaccineScheduleVersion::query()->find($request->input('scheduleVersion'))
            ?? VaccineScheduleVersion::query()->where('status', 'active')->latest('effective_date')->first();
        $filters = $this->locationFilters($request);
        $demand = $analytics->vaccineDemand($user, $months, $version, ...$filters);

        return Pdf::view('reports.demand-forecast-pdf', [
            'demand' => $demand,
            'months' => $months,
            'version' => $version,
            'location' => $this->locationLabel($filters),
        ])->format('a4')->landscape()->margins(8, 8, 8, 8)
            ->name('vaccine-demand-forecast-'.now()->format('Ymd').'.pdf');
    }

    public function schedule(Request $request, ImmunizationSuggestionService $suggestions, PredictiveAnalyticsService $analytics)
    {
        $user = auth()->user();
        abort_unless($user->canViewDefaulters(), 403);
        $filters = $this->locationFilters($request);
        $status = $request->string('status', 'all')->toString();
        $riskFilter = $request->string('risk', 'all')->toString();
        $search = trim($request->string('search')->toString());
        $children = ChildProfile::query()->visibleTo($user)->whereIn('barangay_id', $this->barangayIds($user, $filters))
            ->with(['barangay', 'parents', 'vaccinations'])->withCount('vaccinations')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")))->get();
        $risks = $analytics->missedDoseRisk($user, ...$filters)->keyBy(fn (array $row) => $row['child']->id);
        $rows = $children->map(function (ChildProfile $child) use ($suggestions, $risks): array {
            $suggestion = $suggestions->suggestNextDose($child);
            $risk = $risks->get($child->id);
            $status = $suggestion['status'];

            return ['child' => $child, 'suggestion' => $suggestion, 'status' => $status, 'risk' => $risk,
                'risk_level' => $risk['risk_level'] ?? ($status === 'complete' ? 'not_applicable' : 'low'),
                'contact_channel' => $child->parents->contains(fn ($parent) => filled($parent->phone)) || filled($child->guardian_contact) ? 'SMS priority' : ($child->parents->contains(fn ($parent) => filled($parent->email)) ? 'Email' : 'No contact'),
                'days_late' => in_array($status, ['delayed', 'overdue'], true) && $suggestion['due_at'] ? (int) $suggestion['due_at']->diffInDays(Carbon::today()) : 0];
        })->filter(fn (array $row): bool => ($status === 'all' || $row['status'] === $status) && ($riskFilter === 'all' || $row['risk_level'] === $riskFilter))
            ->sortBy(fn (array $row): array => [$row['status'] === 'overdue' ? 0 : ($row['status'] === 'delayed' ? 1 : 2), $row['suggestion']['due_at']?->timestamp ?? PHP_INT_MAX])->values();

        return Pdf::view('reports.schedule-monitoring-pdf', compact('rows', 'status', 'riskFilter', 'search') + ['location' => $this->locationLabel($filters)])
            ->format('a4')->landscape()->margins(8, 8, 8, 8)->name('schedule-monitoring-'.now()->format('Ymd').'.pdf');
    }

    /** @return array{0:string,1:string,2:string,3:string} */
    private function locationFilters(Request $request): array
    {
        return [$request->string('regionId', 'all')->toString(), $request->string('provinceId', 'all')->toString(), $request->string('municipalityId', 'all')->toString(), $request->string('barangayId', 'all')->toString()];
    }

    private function barangayIds(User $user, array $filters): Collection
    {
        $query = Barangay::query()->whereIn('id', $user->accessibleBarangayIds());
        [$region, $province, $municipality, $barangay] = $filters;
        if ($region !== 'all') {
            $query->whereHas('municipalityRelation.province', fn ($q) => $q->where('region_id', $region));
        }
        if ($province !== 'all') {
            $query->whereHas('municipalityRelation', fn ($q) => $q->where('province_id', $province));
        }
        if ($municipality !== 'all') {
            $query->where('municipality_id', $municipality);
        }
        if ($barangay !== 'all') {
            $query->whereKey($barangay);
        }

        return $query->pluck('id');
    }

    private function locationLabel(array $filters): string
    {
        if ($filters[3] !== 'all') {
            return Barangay::find($filters[3])?->name ?? 'Selected barangay';
        }

        return 'Selected location scope';
    }
}
