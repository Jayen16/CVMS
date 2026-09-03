<?php

namespace App\Livewire;

use App\Models\AdverseEventReport;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\Municipality;
use App\Models\PopulationBackground;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineScheduleVersion;
use App\Models\VaccineType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ReportsPage extends Component
{
    public function render(): View
    {
        $this->authorizeAdmin();

        return view('reports.index', $this->reportData())
            ->layout('layouts.app', [
                'title' => 'Reports',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportData(): array
    {
        $user = auth()->user();
        $validated = request()->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'region_id' => ['nullable', 'string', Rule::in(['all', ...Region::query()->pluck('id')->map(fn ($id) => (string) $id)->all()])],
            'province_id' => ['nullable', 'string', Rule::in(['all', ...Province::query()->pluck('id')->map(fn ($id) => (string) $id)->all()])],
            'municipality_id' => ['nullable', 'string', Rule::in(['all', ...Municipality::query()->pluck('id')->map(fn ($id) => (string) $id)->all()])],
            'barangay_id' => ['nullable', 'string', Rule::in([
                'all',
                ...$user->accessibleBarangayIds()->map(fn ($id) => (string) $id)->all(),
            ])],
            'schedule_version' => [
                'nullable',
                'string',
                Rule::in([
                    'all',
                    'unassigned',
                    ...VaccineScheduleVersion::query()->pluck('id')->map(fn ($id) => (string) $id)->all(),
                ]),
            ],
            'include_aefi' => ['nullable', 'boolean'],
        ]);

        $startDate = filled($validated['start_date'] ?? null)
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : now()->startOfMonth();

        $endDate = filled($validated['end_date'] ?? null)
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : now()->endOfDay();
        $regionFilter = $user->isSuperAdmin() ? ($validated['region_id'] ?? 'all') : 'all';
        $provinceFilter = $user->isSuperAdmin() && $regionFilter !== 'all' ? ($validated['province_id'] ?? 'all') : 'all';
        $municipalityFilter = $user->isSuperAdmin() && $provinceFilter !== 'all' ? ($validated['municipality_id'] ?? 'all') : 'all';
        $barangayFilter = $user->isSuperAdmin() && $municipalityFilter === 'all'
            ? 'all'
            : ($validated['barangay_id'] ?? 'all');
        $accessibleBarangayIds = $user->accessibleBarangayIds();
        $reportBarangayIds = $accessibleBarangayIds;
        if ($regionFilter !== 'all') {
            $reportBarangayIds = Barangay::whereIn('id', $reportBarangayIds)->whereHas('municipalityRelation.province', fn ($query) => $query->where('region_id', $regionFilter))->pluck('id');
        }
        if ($provinceFilter !== 'all') {
            $reportBarangayIds = Barangay::whereIn('id', $reportBarangayIds)->whereHas('municipalityRelation', fn ($query) => $query->where('province_id', $provinceFilter))->pluck('id');
        }
        if ($municipalityFilter !== 'all') {
            $reportBarangayIds = Barangay::whereIn('id', $reportBarangayIds)->where('municipality_id', $municipalityFilter)->pluck('id');
        }
        if ($barangayFilter !== 'all') {
            $reportBarangayIds = $reportBarangayIds->intersect([$barangayFilter])->values();
        }
        $scheduleVersionFilter = $validated['schedule_version'] ?? 'all';
        $includeAefi = (bool) ($validated['include_aefi'] ?? false);

        $recordScope = VaccinationRecord::query()
            ->whereBetween('administered_at', [$startDate->toDateString(), $endDate->toDateString()])
            ->when(
                $scheduleVersionFilter === 'unassigned',
                fn ($query) => $query->whereNull('suggested_schedule_version_id')
            )
            ->when(
                $scheduleVersionFilter !== 'all' && $scheduleVersionFilter !== 'unassigned',
                fn ($query) => $query->where('suggested_schedule_version_id', (int) $scheduleVersionFilter)
            )
            ->whereHas('child', fn ($child) => $child->whereIn('barangay_id', $reportBarangayIds));

        $barangayRecords = VaccinationRecord::query()
            ->select('child_profiles.barangay_id', DB::raw('count(*) as total'))
            ->join('child_profiles', 'vaccination_records.child_profile_id', '=', 'child_profiles.id')
            ->whereBetween('vaccination_records.administered_at', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($scheduleVersionFilter === 'unassigned', fn ($query) => $query->whereNull('vaccination_records.suggested_schedule_version_id'))
            ->when(
                $scheduleVersionFilter !== 'all' && $scheduleVersionFilter !== 'unassigned',
                fn ($query) => $query->where('vaccination_records.suggested_schedule_version_id', (int) $scheduleVersionFilter)
            )
            ->whereIn('child_profiles.barangay_id', $reportBarangayIds)
            ->groupBy('child_profiles.barangay_id')
            ->pluck('total', 'barangay_id');

        $populationYear = PopulationBackground::query()->visibleTo($user)->max('reference_year');
        $population = PopulationBackground::query()->visibleTo($user)->when($populationYear, fn ($query) => $query->where('reference_year', $populationYear))->get();
        $populationTargets = $reportBarangayIds->mapWithKeys(function ($barangayId) use ($population) {
            $barangay = Barangay::find($barangayId);
            $specific = $population->where('barangay_id', $barangayId);
            $rows = $specific->isNotEmpty()
                ? $specific
                : $population->where('barangay_id', null)->where('municipality_id', $barangay?->municipality_id);

            return [$barangayId => (int) $rows->sum('target_population')];
        });
        $coverageRecords = (clone $recordScope)->where('verification_status', '!=', 'rejected')
            ->select('child_profiles.barangay_id', DB::raw('count(*) as total'))
            ->join('child_profiles', 'vaccination_records.child_profile_id', '=', 'child_profiles.id')
            ->groupBy('child_profiles.barangay_id')->pluck('total', 'barangay_id');

        $barangays = Barangay::query()
            ->withCount('children')
            ->withCount(['users as nurses_count' => fn ($query) => $query->notArchived()->whereJsonContains('roles', 'nurse')])
            ->withCount(['users as barangay_admins_count' => fn ($query) => $query->notArchived()->whereJsonContains('roles', 'barangay_admin')])
            ->whereIn('id', $reportBarangayIds)
            ->orderBy('name')
            ->paginate(50)
            ->through(function (Barangay $barangay) use ($barangayRecords, $coverageRecords, $populationTargets) {
                $barangay->report_vaccinations_count = (int) ($barangayRecords[$barangay->id] ?? 0);
                $target = (int) ($populationTargets[$barangay->id] ?? 0);
                $barangay->population_target = $target;
                $barangay->coverage_percent = $target > 0 ? round(((int) ($coverageRecords[$barangay->id] ?? 0) / $target) * 100, 1) : null;

                return $barangay;
            });

        $vaccines = VaccineType::query()
            ->withCount([
                'records as report_records_count' => fn ($query) => $query
                    ->whereBetween('administered_at', [
                        $startDate->toDateString(),
                        $endDate->toDateString(),
                    ])
                    ->when($scheduleVersionFilter === 'unassigned', fn ($builder) => $builder->whereNull('suggested_schedule_version_id'))
                    ->when(
                        $scheduleVersionFilter !== 'all' && $scheduleVersionFilter !== 'unassigned',
                        fn ($builder) => $builder->where('suggested_schedule_version_id', (int) $scheduleVersionFilter)
                    )
                    ->whereHas('child', fn ($child) => $child->whereIn('barangay_id', $reportBarangayIds)),
                'adverseEventReports as report_aefi_count' => fn ($query) => $query
                    ->whereBetween('event_date', [
                        $startDate->toDateString(),
                        $endDate->toDateString(),
                    ])
                    ->whereHas('child', fn ($child) => $child->whereIn('barangay_id', $reportBarangayIds)),
            ])
            ->orderBy('name')
            ->get();

        $verificationCounts = (clone $recordScope)
            ->select('verification_status', DB::raw('count(*) as total'))
            ->groupBy('verification_status')
            ->pluck('total', 'verification_status');

        $sourceCounts = (clone $recordScope)
            ->select('source', DB::raw('count(*) as total'))
            ->groupBy('source')
            ->pluck('total', 'source');

        $versionCounts = (clone $recordScope)
            ->leftJoin('vaccine_schedule_versions', 'vaccination_records.suggested_schedule_version_id', '=', 'vaccine_schedule_versions.id')
            ->selectRaw("coalesce(vaccine_schedule_versions.name, 'Legacy / unspecified') as version_name")
            ->selectRaw("coalesce(vaccine_schedule_versions.version_code, 'legacy') as version_code")
            ->selectRaw('count(*) as total')
            ->groupBy('version_name', 'version_code')
            ->orderByDesc('total')
            ->get();

        $recentRecords = (clone $recordScope)
            ->with(['child.barangay', 'vaccineType', 'recorder', 'suggestedScheduleVersion'])
            ->latest('administered_at')
            ->take(25)
            ->get();

        $aefiScope = AdverseEventReport::query()
            ->whereBetween('event_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereHas('child', fn ($child) => $child->whereIn('barangay_id', $reportBarangayIds));

        $recentAefiReports = $includeAefi
            ? (clone $aefiScope)->with(['child.barangay', 'vaccineType', 'reporter'])->latest('event_date')->take(25)->get()
            : collect();

        $versionOptions = VaccineScheduleVersion::query()
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get();

        $selectedVersion = match ($scheduleVersionFilter) {
            'all' => null,
            'unassigned' => 'Legacy / unspecified',
            default => $versionOptions->firstWhere('id', (int) $scheduleVersionFilter),
        };

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'generatedAt' => now(),
            'populationYear' => $populationYear,
            'barangayFilter' => $barangayFilter,
            'regionFilter' => $regionFilter,
            'provinceFilter' => $provinceFilter,
            'municipalityFilter' => $municipalityFilter,
            'regionOptions' => Region::query()->orderBy('name')->get(),
            'provinceOptions' => $user->isSuperAdmin() && $regionFilter !== 'all'
                ? Province::query()->where('region_id', $regionFilter)->orderBy('name')->get()
                : collect(),
            'municipalityOptions' => $user->isSuperAdmin() && $provinceFilter !== 'all'
                ? Municipality::query()->where('province_id', $provinceFilter)->orderBy('name')->get()
                : collect(),
            'barangayOptions' => ($user->isSuperAdmin() && $municipalityFilter !== 'all') || $user->isMunicipalAdmin()
                ? Barangay::query()->whereIn('id', $accessibleBarangayIds)->when($municipalityFilter !== 'all', fn ($query) => $query->where('municipality_id', $municipalityFilter))->orderBy('name')->get()
                : collect(),
            'scheduleVersionFilter' => $scheduleVersionFilter,
            'includeAefi' => $includeAefi,
            'scheduleVersionOptions' => $versionOptions,
            'selectedScheduleVersion' => $selectedVersion,
            'stats' => [
                'barangays' => $reportBarangayIds->count(),
                'barangayAdmins' => User::notArchived()->whereIn('barangay_id', $reportBarangayIds)->whereJsonContains('roles', 'barangay_admin')->count(),
                'nurses' => User::notArchived()->whereIn('barangay_id', $reportBarangayIds)->whereJsonContains('roles', 'nurse')->count(),
                'children' => ChildProfile::whereIn('barangay_id', $reportBarangayIds)->count(),
                'vaccinations' => (clone $recordScope)->count(),
                'aefi' => $includeAefi ? (clone $aefiScope)->count() : 0,
                'pending' => VaccinationRecord::where('verification_status', 'pending')
                    ->when($scheduleVersionFilter === 'unassigned', fn ($query) => $query->whereNull('suggested_schedule_version_id'))
                    ->when(
                        $scheduleVersionFilter !== 'all' && $scheduleVersionFilter !== 'unassigned',
                        fn ($query) => $query->where('suggested_schedule_version_id', (int) $scheduleVersionFilter)
                    )
                    ->whereHas('child', fn ($child) => $child->whereIn('barangay_id', $reportBarangayIds))
                    ->count(),
                'populationTarget' => (int) $populationTargets->sum(),
                'coveragePercent' => $populationTargets->sum() > 0 ? round(($coverageRecords->sum() / $populationTargets->sum()) * 100, 1) : null,
            ],
            'barangays' => $barangays,
            'vaccines' => $vaccines,
            'verificationCounts' => $verificationCounts,
            'sourceCounts' => $sourceCounts,
            'versionCounts' => $versionCounts,
            'recentRecords' => $recentRecords,
            'recentAefiReports' => $recentAefiReports,
        ];
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->canViewOversight(), 403);
    }
}
