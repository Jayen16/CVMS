<?php

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\VaccineScheduleVersion;
use App\Services\PredictiveAnalyticsService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class PredictiveAnalyticsPage extends Component
{
    #[Url]
    public int $months = 3;

    #[Url]
    public ?string $scheduleVersion = null;

    #[Url] public string $regionId = 'all';
    #[Url] public string $provinceId = 'all';
    #[Url] public string $municipalityId = 'all';
    #[Url] public string $barangayId = 'all';

    public function updatedRegionId(): void
    {
        $this->provinceId = 'all';
        $this->municipalityId = 'all';
        $this->barangayId = 'all';
    }

    public function updatedProvinceId(): void
    {
        $this->municipalityId = 'all';
        $this->barangayId = 'all';
    }

    public function updatedMunicipalityId(): void
    {
        $this->barangayId = 'all';
    }

    public function render(PredictiveAnalyticsService $analytics): View
    {
        abort_unless(auth()->user()->canViewDefaulters(), 403);

        $months = in_array($this->months, [1, 3, 6, 12], true) ? $this->months : 3;
        $versions = VaccineScheduleVersion::query()->orderByDesc('effective_date')->orderByDesc('id')->get();
        $selectedVersion = $versions->firstWhere('id', $this->scheduleVersion)
            ?? $versions->firstWhere('status', 'active');

        $regions = auth()->user()->isSuperAdmin() ? Region::query()->orderBy('name')->get() : collect();
        $provinces = auth()->user()->isSuperAdmin() ? Province::query()->when($this->regionId !== 'all', fn ($query) => $query->where('region_id', $this->regionId))->orderBy('name')->get() : collect();
        $municipalities = auth()->user()->isSuperAdmin() ? Municipality::query()
            ->when($this->regionId !== 'all', fn ($query) => $query->whereHas('province', fn ($province) => $province->where('region_id', $this->regionId)))
            ->when($this->provinceId !== 'all', fn ($query) => $query->where('province_id', $this->provinceId))
            ->orderBy('name')->get() : collect();
        $barangays = Barangay::query()
            ->whereIn('id', auth()->user()->accessibleBarangayIds())
            ->when(auth()->user()->isSuperAdmin() && $this->municipalityId !== 'all', fn ($query) => $query->where('municipality_id', $this->municipalityId))
            ->orderBy('name')->get();

        return view('livewire.predictive-analytics-page', [
            'demand' => $analytics->vaccineDemand(auth()->user(), $months, $selectedVersion, $this->regionId, $this->provinceId, $this->municipalityId, $this->barangayId),
            'forecastMonths' => $months,
            'scheduleVersions' => $versions,
            'selectedVersion' => $selectedVersion,
            'regions' => $regions,
            'provinces' => $provinces,
            'municipalities' => $municipalities,
            'barangays' => $barangays,
        ])->layout('layouts.app', ['title' => 'Vaccine demand forecast']);
    }
}
