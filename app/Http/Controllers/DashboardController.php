<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\VaccinationRecord;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();

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
                ],
                'barangays' => $barangays,
            ]);
        }

        if ($user->isParent()) {
            $children = $user->linkedChildren()
                ->withCount('vaccinations')
                ->latest()
                ->get();

            return view('dashboard', [
                'role' => 'parent',
                'stats' => [
                    'children' => $children->count(),
                    'vaccinations' => VaccinationRecord::whereHas('child.parents', fn ($query) => $query->whereKey($user->id))->count(),
                ],
                'children' => $children,
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
            ],
            'children' => $children,
        ]);
    }
}
