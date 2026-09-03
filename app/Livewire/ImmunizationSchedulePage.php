<?php

namespace App\Livewire;

use App\Models\ChildProfile;
use App\Services\ImmunizationSuggestionService;
use App\Services\PredictiveAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

class ImmunizationSchedulePage extends Component
{
    #[Url] public string $search = '';
    #[Url] public string $status = 'all';
    #[Url] public string $risk = 'all';

    public function render(ImmunizationSuggestionService $suggestions, PredictiveAnalyticsService $analytics): View
    {
        abort_unless(auth()->user()->canViewDefaulters(), 403);

        $children = ChildProfile::query()->visibleTo(auth()->user())
            ->with(['barangay', 'parents', 'vaccinations'])->withCount('vaccinations')
            ->when(trim($this->search) !== '', function ($query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(fn ($child) => $child->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term));
            })->get();
        $risks = $analytics->missedDoseRisk(auth()->user())->keyBy(fn (array $row) => $row['child']->id);
        $rows = $children->map(function (ChildProfile $child) use ($suggestions, $risks): array {
            $suggestion = $suggestions->suggestNextDose($child);
            $risk = $risks->get($child->id);
            $status = $suggestion['status'];
            return [
                'child' => $child, 'suggestion' => $suggestion, 'status' => $status, 'risk' => $risk,
                'risk_level' => $risk['risk_level'] ?? ($status === 'complete' ? 'not_applicable' : 'low'),
                'contact_channel' => $this->contactChannel($child),
                'days_late' => in_array($status, ['delayed', 'overdue'], true) && $suggestion['due_at'] ? (int) $suggestion['due_at']->diffInDays(Carbon::today()) : 0,
            ];
        })->filter(fn (array $row): bool => ($this->status === 'all' || $row['status'] === $this->status) && ($this->risk === 'all' || $row['risk_level'] === $this->risk))
            ->sortBy(fn (array $row): array => [$row['status'] === 'overdue' ? 0 : ($row['status'] === 'delayed' ? 1 : 2), $row['suggestion']['due_at']?->timestamp ?? PHP_INT_MAX])->values();

        return view('livewire.immunization-schedule-page', ['rows' => $rows, 'totalChildren' => $children->count()])->layout('layouts.app', ['title' => 'Schedule monitoring']);
    }

    private function contactChannel(ChildProfile $child): string
    {
        if ($child->parents->contains(fn ($parent) => filled($parent->phone)) || filled($child->guardian_contact)) return 'SMS priority';
        if ($child->parents->contains(fn ($parent) => filled($parent->email))) return 'Email';
        return 'No contact';
    }
}
