<?php

namespace App\Livewire;

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

    public function render(PredictiveAnalyticsService $analytics): View
    {
        abort_unless(auth()->user()->canViewDefaulters(), 403);

        $months = in_array($this->months, [1, 3, 6, 12], true) ? $this->months : 3;
        $versions = VaccineScheduleVersion::query()->orderByDesc('effective_date')->orderByDesc('id')->get();
        $selectedVersion = $versions->firstWhere('id', $this->scheduleVersion)
            ?? $versions->firstWhere('status', 'active');

        return view('livewire.predictive-analytics-page', [
            'demand' => $analytics->vaccineDemand(auth()->user(), $months, $selectedVersion),
            'forecastMonths' => $months,
            'scheduleVersions' => $versions,
            'selectedVersion' => $selectedVersion,
        ])->layout('layouts.app', ['title' => 'Vaccine demand forecast']);
    }
}
