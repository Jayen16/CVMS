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
                'announcements' => $announcements,
            ])->layout('layouts.app', ['title' => 'Dashboard']);
        }

        if ($user->isMunicipalAdmin()) {
            $children = ChildProfile::query()->visibleTo($user)->withCount('vaccinations')->latest()->take(8)->get();

            return view('livewire.dashboard-page', [
                'role' => 'municipal_admin',
                'stats' => [
                    'municipality' => $user->municipality()->value('name') ?? 'Unassigned',
                    'nurses' => User::notArchived()->where('municipality_id', $user->municipality_id)->whereJsonContains('roles', 'nurse')->count(),
                    'children' => ChildProfile::query()->visibleTo($user)->count(),
                    'vaccinations' => VaccinationRecord::whereHas('child', fn ($query) => $query->whereIn('barangay_id', $user->accessibleBarangayIds()))->count(),
                    'pending' => VaccinationRecord::where('verification_status', 'pending')->whereHas('child', fn ($query) => $query->whereIn('barangay_id', $user->accessibleBarangayIds()))->count(),
                    'pendingSync' => $pendingSync,
                ],
                'children' => $children,
                'announcements' => $announcements,
            ])->layout('layouts.app', ['title' => 'Dashboard']);
        }

        if ($user->isBarangayAdmin() && ! $user->isNurse()) {
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
                'children' => ChildProfile::query()
                    ->where('barangay_id', $user->barangay_id)
                    ->withCount('vaccinations')
                    ->latest()
                    ->take(8)
                    ->get(),
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
            'announcements' => $announcements,
        ])->layout('layouts.app', ['title' => 'Dashboard']);
    }
}
