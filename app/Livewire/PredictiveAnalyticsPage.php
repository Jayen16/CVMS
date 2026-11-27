<?php

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\VaccineScheduleVersion;
use App\Services\GeminiPlanningSummaryService;
use App\Services\PredictiveAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

class PredictiveAnalyticsPage extends Component
{
    #[Url]
    public int $months = 3;

    #[Url]
    public ?string $scheduleVersion = null;

    #[Url]
    public string $regionId = 'all';

    #[Url]
    public string $provinceId = 'all';

    #[Url]
    public string $municipalityId = 'all';

    #[Url]
    public string $barangayId = 'all';

    #[Locked]
    public ?string $aiSummary = null;

    #[Locked]
    public ?string $aiGeneratedAt = null;

    #[Locked]
    public ?string $aiError = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['months', 'scheduleVersion', 'regionId', 'provinceId', 'municipalityId', 'barangayId'], true)) {
            $this->reset('aiSummary', 'aiGeneratedAt', 'aiError');
        }
    }

    public function generateAiSummary(PredictiveAnalyticsService $analytics, GeminiPlanningSummaryService $gemini): void
    {
        $user = auth()->user();
        abort_unless($user->canViewDefaulters(), 403);
        $this->reset('aiSummary', 'aiGeneratedAt', 'aiError');

        if ($user->isSuperAdmin() && $this->regionId === 'all') {
            $this->aiError = 'Select a region before generating a summary.';

            return;
        }

        $limitKey = 'gemini-planning:'.$user->id;
        if (RateLimiter::tooManyAttempts($limitKey, 3)) {
            $this->aiError = 'Please wait a minute before generating another summary.';

            return;
        }

        $months = in_array($this->months, [1, 3, 6, 12], true) ? $this->months : 3;
        $versions = VaccineScheduleVersion::query()->orderByDesc('effective_date')->orderByDesc('id')->get();
        $version = $versions->firstWhere('id', $this->scheduleVersion) ?? $versions->firstWhere('status', 'active');
        $demand = $analytics->vaccineDemand($user, $months, $version, $this->regionId, $this->provinceId, $this->municipalityId, $this->barangayId);

        RateLimiter::hit($limitKey, 60);

        try {
            $this->aiSummary = $gemini->generate($demand, $months);
            $this->aiGeneratedAt = now()->timezone('Asia/Manila')->format('M d, Y g:i A').' (Philippine time)';
        } catch (RuntimeException $exception) {
            $this->aiError = $exception->getMessage();
        }
    }

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
        $user = auth()->user();
        abort_unless($user->canViewDefaulters(), 403);

        $months = in_array($this->months, [1, 3, 6, 12], true) ? $this->months : 3;
        $versions = VaccineScheduleVersion::query()->orderByDesc('effective_date')->orderByDesc('id')->get();
        $selectedVersion = $versions->firstWhere('id', $this->scheduleVersion)
            ?? $versions->firstWhere('status', 'active');

        $regions = $user->isSuperAdmin() ? Region::query()->orderBy('name')->get() : collect();
        $provinces = $user->isSuperAdmin() ? Province::query()->when($this->regionId !== 'all', fn ($query) => $query->where('region_id', $this->regionId))->orderBy('name')->get() : collect();
        $municipalities = $user->isSuperAdmin() ? Municipality::query()
            ->when($this->regionId !== 'all', fn ($query) => $query->whereHas('province', fn ($province) => $province->where('region_id', $this->regionId)))
            ->when($this->provinceId !== 'all', fn ($query) => $query->where('province_id', $this->provinceId))
            ->orderBy('name')->get() : collect();
        $barangays = Barangay::query()
            ->whereIn('id', $user->accessibleBarangayIds())
            ->when($user->isSuperAdmin() && $this->municipalityId !== 'all', fn ($query) => $query->where('municipality_id', $this->municipalityId))
            ->orderBy('name')->get();

        $requiresLocationSelection = $user->isSuperAdmin() && $this->regionId === 'all';

        $demand = $requiresLocationSelection ? collect() : $analytics->vaccineDemand($user, $months, $selectedVersion, $this->regionId, $this->provinceId, $this->municipalityId, $this->barangayId);

        return view('livewire.predictive-analytics-page', [
            'demand' => $demand,
            'forecastChart' => $demand->map(fn (array $row): array => [
                'label' => $row['vaccine']->name,
                'demand' => (int) $row['estimated_demand'],
                'stock' => (int) $row['available_stock'],
                'status' => $row['stock_status'],
            ])->all(),
            'forecastMonths' => $months,
            'scheduleVersions' => $versions,
            'selectedVersion' => $selectedVersion,
            'regions' => $regions,
            'provinces' => $provinces,
            'municipalities' => $municipalities,
            'barangays' => $barangays,
            'requiresLocationSelection' => $requiresLocationSelection,
        ])->layout('layouts.app', ['title' => 'Vaccine Demand Forecast']);
    }
}
