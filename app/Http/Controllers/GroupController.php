<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Region;
use App\Models\User;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->canManageGroups(), 403);

        $filters = request()->validate([
            'region' => ['nullable', 'exists:regions,id'],
            'province' => ['nullable', 'exists:provinces,id'],
            'municipality' => ['nullable', 'exists:municipalities,id'],
            'barangay' => ['nullable', 'exists:barangays,id'],
        ]);
        $allRegions = Region::with(['provinces.municipalities.facilities', 'provinces.municipalities.barangays.facilities'])->orderBy('name')->get();
        $locationTree = $allRegions->map(fn ($region) => [
            'id' => $region->id, 'name' => $region->name,
            'provinces' => $region->provinces->map(fn ($province) => [
                'id' => $province->id, 'name' => $province->name,
                'municipalities' => $province->municipalities->map(fn ($municipality) => [
                    'id' => $municipality->id, 'name' => $municipality->name,
                    'barangays' => $municipality->barangays->map(fn ($barangay) => ['id' => $barangay->id, 'name' => $barangay->name])->values(),
                ])->values(),
            ])->values(),
        ])->values();
        $regions = $allRegions;
        if (filled($filters['region'] ?? null)) {
            $regions = $regions->where('id', $filters['region'])->values();
        }
        if (filled($filters['province'] ?? null)) {
            $regions = $regions->map(function (Region $region) use ($filters) {
                $region->setRelation('provinces', $region->provinces->where('id', $filters['province'])->values());

                return $region;
            })->filter(fn (Region $region) => $region->provinces->isNotEmpty())->values();
        }
        if (filled($filters['municipality'] ?? null)) {
            $regions = $regions->map(function (Region $region) use ($filters) {
                $region->provinces->each(fn ($province) => $province->setRelation('municipalities', $province->municipalities->where('id', $filters['municipality'])->values()));

                return $region;
            })->filter(fn (Region $region) => $region->provinces->contains(fn ($province) => $province->municipalities->isNotEmpty()))->values();
        }
        if (filled($filters['barangay'] ?? null)) {
            $regions = $regions->map(function (Region $region) use ($filters) {
                $region->provinces->each(fn ($province) => $province->municipalities->each(fn ($municipality) => $municipality->setRelation('barangays', $municipality->barangays->where('id', $filters['barangay'])->values())));

                return $region;
            })->filter(fn (Region $region) => $region->provinces->contains(fn ($province) => $province->municipalities->contains(fn ($municipality) => $municipality->barangays->isNotEmpty())))->values();
        }
        $users = User::notArchived()->with(['barangay', 'municipality'])->orderBy('name')->get();
        $municipalityOptions = Municipality::orderBy('name')->pluck('name', 'id');
        $barangayOptions = Barangay::orderBy('name')->pluck('name', 'id');

        return view('groups.index', compact('regions', 'users', 'filters', 'municipalityOptions', 'barangayOptions', 'locationTree'));
    }
}
