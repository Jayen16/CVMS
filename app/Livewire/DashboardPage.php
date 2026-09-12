<?php

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\ClinicAnnouncement;
use App\Models\OfflineSyncOutbox;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Services\ImmunizationSuggestionService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class DashboardPage extends Component
{
    private function statusChart($query): array
    {
        return collect(['verified', 'pending', 'rejected'])
            ->map(fn (string $status): array => [
                'label' => ucfirst($status),
                'value' => (clone $query)->where('verification_status', $status)->count(),
                'color' => match ($status) {
                    'verified' => 'bg-emerald-500',
                    'pending' => 'bg-amber-500',
                    default => 'bg-red-500',
                },
            ])->all();
    }

    private function monthlyVaccinationChart($query): array
    {
        $start = today()->subMonths(5)->startOfMonth();
        $records = (clone $query)
            ->whereBetween('administered_at', [$start, today()->endOfMonth()])
            ->get(['administered_at']);

        return collect(range(0, 5))->map(function (int $monthsAgo) use ($start, $records): array {
            $month = $start->copy()->addMonths($monthsAgo);

            return [
                'label' => $month->format('M Y'),
                'value' => $records->filter(fn (VaccinationRecord $record): bool => $record->administered_at?->isSameMonth($month) ?? false)->count(),
            ];
        })->all();
    }

    private function immunizationStatusChart($children, ImmunizationSuggestionService $suggestions): array
    {
        $labels = [
            'complete' => 'Up to date',
            'upcoming' => 'Upcoming',
            'due' => 'Due now',
            'delayed' => 'Delayed',
            'overdue' => 'Overdue',
            'catch_up_review' => 'Catch-up review',
        ];
        $counts = collect($labels)->mapWithKeys(fn (string $label, string $status): array => [$status => 0]);

        foreach ($children as $child) {
            $status = $suggestions->suggestNextDose($child)['status'];
            $counts->put($status, ($counts->get($status) ?? 0) + 1);
        }

        return collect($labels)
            ->map(fn (string $label, string $status): array => [
                'label' => $label,
                'value' => $counts->get($status, 0),
            ])
            ->values()
            ->all();
    }

    public function render(ImmunizationSuggestionService $suggestions): View
    {
        $user = auth()->user();
        $announcements = ClinicAnnouncement::query()
            ->with(['barangay', 'region', 'province', 'municipality'])
            ->where('active', true)
            ->whereDate('starts_on', '<=', today()->addDays(30))
            ->where(function ($query) {
                $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today());
            })
            ->when($user->isParent(), fn ($query) => $query->whereIn('audience', ['all', 'parents']))
            ->when($user->isNurse() || $user->isBarangayAdmin(), fn ($query) => $query->whereIn('audience', ['all', 'staff']))
            ->visibleTo($user)
            ->orderBy('starts_on')
            ->take(6)
            ->get();

        $pendingSync = config('offline.enabled')
            ? OfflineSyncOutbox::whereNull('synced_at')->count()
            : 0;
        if ($user->isSuperAdmin()) {
            return view('livewire.dashboard-page', [
                'role' => 'superadmin',
                'stats' => [
                    'barangays' => Barangay::count(),
                    'barangayAdmins' => User::notArchived()->whereJsonContains('roles', 'barangay_admin')->count(),
                    'nurses' => User::notArchived()->whereJsonContains('roles', 'nurse')->count(),
                    'children' => ChildProfile::count(),
                    'vaccinations' => VaccinationRecord::count(),
                    'pending' => VaccinationRecord::where('verification_status', 'pending')->count(),
                    'pendingSync' => $pendingSync,
                ],
                'barangays' => Barangay::query()
                    ->withCount('children')
                    ->withCount('vaccinations')
                    ->withCount(['users as barangay_admins_count' => fn ($query) => $query->notArchived()->whereJsonContains('roles', 'barangay_admin')])
                    ->withCount(['users as nurses_count' => fn ($query) => $query->notArchived()->whereJsonContains('roles', 'nurse')])
                    ->orderBy('name')
                    ->paginate(50),
                'statusChart' => $this->statusChart(VaccinationRecord::query()),
                'announcements' => $announcements,
            ])->layout('layouts.app', ['title' => 'Dashboard']);
        }

        if ($user->isParent()) {
            $children = $user->linkedChildren()
                ->with('vaccinations.vaccineType')
                ->withCount('vaccinations')
                ->latest()
                ->get();

            $calendarItems = $children->map(function (ChildProfile $child) use ($suggestions) {
                $suggestion = $suggestions->suggestNextDose($child);
                $actionDate = $suggestion['action_at'];

                if ($actionDate === null || ! $actionDate->isSameMonth(Carbon::today())) {
                    return null;
                }

                return [
                    'date' => $actionDate->toDateString(),
                    'child' => $child,
                    'suggestion' => $suggestion,
                ];
            })->filter()->groupBy('date')->sortKeys();

            return view('livewire.dashboard-page', [
                'role' => 'parent',
                'stats' => [
                    'children' => $children->count(),
                    'vaccinations' => VaccinationRecord::whereHas('child.parents', fn ($query) => $query->whereKey($user->id))->count(),
                    'pendingSync' => $pendingSync,
                ],
                'children' => $children,
                'calendarItems' => $calendarItems,
                'statusChart' => $this->statusChart(VaccinationRecord::whereHas('child.parents', fn ($query) => $query->whereKey($user->id))),
                'announcements' => $announcements,
            ])->layout('layouts.app', ['title' => 'Dashboard']);
        }

        if ($user->isMunicipalAdmin()) {
            $children = ChildProfile::query()->visibleTo($user)->withCount('vaccinations')->latest()->take(8)->get();

            return view('livewire.dashboard-page', [
                'role' => 'municipal_admin',
                'stats' => [
                    'municipality' => $user->municipality()->value('name') ?? 'Unassigned',
                    'barangays' => Barangay::where('municipality_id', $user->municipality_id)->count(),
                    'barangayAdmins' => User::notArchived()->where('municipality_id', $user->municipality_id)->whereJsonContains('roles', 'barangay_admin')->count(),
                    'nurses' => User::notArchived()->where('municipality_id', $user->municipality_id)->whereJsonContains('roles', 'nurse')->count(),
                    'children' => ChildProfile::query()->visibleTo($user)->count(),
                    'vaccinations' => VaccinationRecord::whereHas('child', fn ($query) => $query->whereIn('barangay_id', $user->accessibleBarangayIds()))->count(),
                    'pending' => VaccinationRecord::where('verification_status', 'pending')->whereHas('child', fn ($query) => $query->whereIn('barangay_id', $user->accessibleBarangayIds()))->count(),
                    'pendingSync' => $pendingSync,
                ],
                'barangays' => Barangay::query()
                    ->where('municipality_id', $user->municipality_id)
                    ->withCount('children')
                    ->withCount('vaccinations')
                    ->withCount(['users as barangay_admins_count' => fn ($query) => $query->notArchived()->whereJsonContains('roles', 'barangay_admin')])
                    ->withCount(['users as nurses_count' => fn ($query) => $query->notArchived()->whereJsonContains('roles', 'nurse')])
                    ->orderBy('name')
                    ->get(),
                'children' => $children,
                'statusChart' => $this->statusChart(VaccinationRecord::whereHas('child', fn ($query) => $query->whereIn('barangay_id', $user->accessibleBarangayIds()))),
                'announcements' => $announcements,
            ])->layout('layouts.app', ['title' => 'Dashboard']);
        }

        if ($user->isBarangayAdmin() && ! $user->isNurse()) {
            $barangayVaccinations = VaccinationRecord::whereHas('child', fn ($query) => $query->where('barangay_id', $user->barangay_id));
            $barangayChildren = ChildProfile::query()
                ->where('barangay_id', $user->barangay_id)
                ->withCount('vaccinations')
                ->get();
            $sexCounts = $barangayChildren->countBy(fn (ChildProfile $child): string => strtolower((string) $child->sex));

            return view('livewire.dashboard-page', [
                'role' => 'barangay_admin',
                'stats' => [
                    'barangay' => $user->barangay()->value('name') ?? 'Unassigned',
                    'nurses' => User::notArchived()->where('barangay_id', $user->barangay_id)->whereJsonContains('roles', 'nurse')->count(),
                    'children' => ChildProfile::where('barangay_id', $user->barangay_id)->count(),
                    'vaccinations' => VaccinationRecord::whereHas('child', fn ($query) => $query->where('barangay_id', $user->barangay_id))->count(),
                    'pending' => VaccinationRecord::where('verification_status', 'pending')
                        ->whereHas('child', fn ($query) => $query->where('barangay_id', $user->barangay_id))
                        ->count(),
                    'pendingSync' => $pendingSync,
                ],
                'statusChart' => $this->statusChart(VaccinationRecord::whereHas('child', fn ($query) => $query->where('barangay_id', $user->barangay_id))),
                'immunizationStatusChart' => $this->immunizationStatusChart($barangayChildren, $suggestions),
                'monthlyVaccinationChart' => $this->monthlyVaccinationChart($barangayVaccinations),
                'sexChart' => [
                    ['label' => 'Male', 'value' => (int) $sexCounts->get('male', 0), 'color' => '#14b8a6'],
                    ['label' => 'Female', 'value' => (int) $sexCounts->get('female', 0), 'color' => '#f472b6'],
                ],
                'announcements' => $announcements,
            ])->layout('layouts.app', ['title' => 'Dashboard']);
        }

        $children = ChildProfile::query()
            ->where('barangay_id', $user->barangay_id)
            ->withCount('vaccinations')
            ->latest()
            ->take(8)
            ->get();

        return view('livewire.dashboard-page', [
            'role' => 'nurse',
            'stats' => [
                'children' => ChildProfile::where('barangay_id', $user->barangay_id)->count(),
                'vaccinations' => VaccinationRecord::whereHas('child', fn ($query) => $query->where('barangay_id', $user->barangay_id))->count(),
                'barangay' => $user->barangay()->value('name') ?? 'Unassigned',
                'pending' => VaccinationRecord::where('verification_status', 'pending')
                    ->whereHas('child', fn ($query) => $query->where('barangay_id', $user->barangay_id))
                    ->count(),
                'pendingSync' => $pendingSync,
            ],
            'children' => $children,
            'statusChart' => $this->statusChart(VaccinationRecord::whereHas('child', fn ($query) => $query->where('barangay_id', $user->barangay_id))),
            'announcements' => $announcements,
        ])->layout('layouts.app', ['title' => 'Dashboard']);
    }
}
