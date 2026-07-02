<?php

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\AdverseEventReport;
use App\Models\ChildProfile;
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
            ->when(
                ! $user->isSuperAdmin(),
                fn ($query) => $query->whereHas('child', fn ($child) => $child->where('barangay_id', $user->barangay_id))
            );

        $barangayRecords = VaccinationRecord::query()
            ->select('child_profiles.barangay_id', DB::raw('count(*) as total'))
            ->join('child_profiles', 'vaccination_records.child_profile_id', '=', 'child_profiles.id')
            ->whereBetween('vaccination_records.administered_at', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($scheduleVersionFilter === 'unassigned', fn ($query) => $query->whereNull('vaccination_records.suggested_schedule_version_id'))
            ->when(
                $scheduleVersionFilter !== 'all' && $scheduleVersionFilter !== 'unassigned',
                fn ($query) => $query->where('vaccination_records.suggested_schedule_version_id', (int) $scheduleVersionFilter)
            )
            ->when(! $user->isSuperAdmin(), fn ($query) => $query->where('child_profiles.barangay_id', $user->barangay_id))
            ->groupBy('child_profiles.barangay_id')
            ->pluck('total', 'barangay_id');

        $barangays = Barangay::query()
            ->withCount('children')
            ->withCount(['users as nurses_count' => fn ($query) => $query->whereJsonContains('roles', 'nurse')])
            ->withCount(['users as barangay_admins_count' => fn ($query) => $query->whereJsonContains('roles', 'barangay_admin')])
            ->when(! $user->isSuperAdmin(), fn ($query) => $query->whereKey($user->barangay_id))
            ->orderBy('name')
            ->get()
            ->map(function (Barangay $barangay) use ($barangayRecords) {
                $barangay->report_vaccinations_count = (int) ($barangayRecords[$barangay->id] ?? 0);

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
                    ->when(! $user->isSuperAdmin(), fn ($builder) => $builder->whereHas('child', fn ($child) => $child->where('barangay_id', $user->barangay_id))),
                'adverseEventReports as report_aefi_count' => fn ($query) => $query
                    ->whereBetween('event_date', [
                        $startDate->toDateString(),
                        $endDate->toDateString(),
                    ])
                    ->when(! $user->isSuperAdmin(), fn ($builder) => $builder->whereHas('child', fn ($child) => $child->where('barangay_id', $user->barangay_id))),
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
            ->when(
                ! $user->isSuperAdmin(),
                fn ($query) => $query->whereHas('child', fn ($child) => $child->where('barangay_id', $user->barangay_id))
            );

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
            'scheduleVersionFilter' => $scheduleVersionFilter,
            'includeAefi' => $includeAefi,
            'scheduleVersionOptions' => $versionOptions,
            'selectedScheduleVersion' => $selectedVersion,
            'stats' => [
                'barangays' => $user->isSuperAdmin() ? Barangay::count() : 1,
                'barangayAdmins' => $user->isSuperAdmin()
                    ? User::whereJsonContains('roles', 'barangay_admin')->count()
                    : User::where('barangay_id', $user->barangay_id)->whereJsonContains('roles', 'barangay_admin')->count(),
                'nurses' => $user->isSuperAdmin()
                    ? User::whereJsonContains('roles', 'nurse')->count()
                    : User::where('barangay_id', $user->barangay_id)->whereJsonContains('roles', 'nurse')->count(),
                'children' => $user->isSuperAdmin()
                    ? ChildProfile::count()
                    : ChildProfile::where('barangay_id', $user->barangay_id)->count(),
                'vaccinations' => (clone $recordScope)->count(),
                'aefi' => $includeAefi ? (clone $aefiScope)->count() : 0,
                'pending' => VaccinationRecord::where('verification_status', 'pending')
                    ->when($scheduleVersionFilter === 'unassigned', fn ($query) => $query->whereNull('suggested_schedule_version_id'))
                    ->when(
                        $scheduleVersionFilter !== 'all' && $scheduleVersionFilter !== 'unassigned',
                        fn ($query) => $query->where('suggested_schedule_version_id', (int) $scheduleVersionFilter)
                    )
                    ->when(! $user->isSuperAdmin(), fn ($query) => $query->whereHas('child', fn ($child) => $child->where('barangay_id', $user->barangay_id)))
                    ->count(),
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
