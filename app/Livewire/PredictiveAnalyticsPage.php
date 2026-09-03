<?php

namespace App\Livewire;

use App\Services\PredictiveAnalyticsService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PredictiveAnalyticsPage extends Component
{
    public function render(PredictiveAnalyticsService $analytics): View
    {
        abort_unless(auth()->user()->canViewDefaulters(), 403);

        return view('livewire.predictive-analytics-page', [
            'demand' => $analytics->vaccineDemand(auth()->user()),
            'risks' => $analytics->missedDoseRisk(auth()->user()),
        ])->layout('layouts.app', ['title' => 'Predictive analytics']);
    }
}
