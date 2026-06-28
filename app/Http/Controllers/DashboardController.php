<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\ClinicAnnouncement;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Services\ImmunizationSuggestionService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ImmunizationSuggestionService $suggestions): View
    {
        $user = auth()->user();
        $announcements = ClinicAnnouncement::query()
            ->with('barangay')
            ->where('active', true)
            ->whereDate('starts_on', '<=', today()->addDays(30))
            ->where(function ($query) {
                $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today());
            })
            ->when($user->isParent(), fn ($query) => $query->whereIn('audience', ['all', 'parents']))
            ->when($user->isNurse(), fn ($query) => $query->whereIn('audience', ['all', 'staff']))
            ->when($user->isNurse(), fn ($query) => $query->where(function ($builder) use ($user) {
                $builder->whereNull('barangay_id')->orWhere('barangay_id', $user->barangay_id);
            }))
            ->orderBy('starts_on')
            ->take(6)
            ->get();

        if ($user->isAdmin()) {
            $barangays = Barangay::query()
                ->withCount(['children', 'nurses'])
                ->with(['children.vaccinations'])
                ->orderBy('name')
                ->get();

            return view('dashboard', [
                'role' => 'admin',
                'stats' => [
                    'barangays' => Barangay::count(),
                    'nurses' => User::where('role', 'nurse')->count(),
                    'children' => ChildProfile::count(),
                    'vaccinations' => VaccinationRecord::count(),
                    'pending' => VaccinationRecord::where('verification_status', 'pending')->count(),
                ],
                'barangays' => $barangays,
                'announcements' => $announcements,
            ]);
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

            return view('dashboard', [
                'role' => 'parent',
                'stats' => [
                    'children' => $children->count(),
                    'vaccinations' => VaccinationRecord::whereHas('child.parents', fn ($query) => $query->whereKey($user->id))->count(),
                ],
                'children' => $children,
                'calendarItems' => $calendarItems,
                'announcements' => $announcements,
            ]);
        }

        $children = ChildProfile::query()
            ->where('barangay_id', $user->barangay_id)
            ->withCount('vaccinations')
            ->latest()
            ->take(8)
            ->get();

        $barangayName = $user->barangay()
            ->value('name') ?? 'Unassigned';

        return view('dashboard', [
            'role' => 'nurse',
            'stats' => [
                'children' => ChildProfile::where('barangay_id', $user->barangay_id)->count(),
                'vaccinations' => VaccinationRecord::whereHas('child', fn ($query) => $query->where('barangay_id', $user->barangay_id))->count(),
                'barangay' => $barangayName,
                'pending' => VaccinationRecord::where('verification_status', 'pending')
                    ->whereHas('child', fn ($query) => $query->where('barangay_id', $user->barangay_id))
                    ->count(),
            ],
            'children' => $children,
            'announcements' => $announcements,
        ]);
    }
}
