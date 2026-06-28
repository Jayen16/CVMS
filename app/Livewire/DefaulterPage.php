<?php

namespace App\Livewire;

use App\Models\ChildProfile;
use App\Services\ImmunizationSuggestionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Defaulters')]
class DefaulterPage extends Component
{
    #[Url]
    public int $days = 7;

    public function setThreshold(int $days): void
    {
        if (in_array($days, [7, 14, 30], true)) {
            $this->days = $days;
        }
    }

    public function render(ImmunizationSuggestionService $suggestions): View
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isNurse(), 403);

        $threshold = in_array($this->days, [7, 14, 30], true) ? $this->days : 7;
        $children = ChildProfile::query()
            ->with(['barangay', 'parents'])
            ->when(auth()->user()->isNurse(), fn ($query) => $query->where('barangay_id', auth()->user()->barangay_id))
            ->get();

        $today = Carbon::today();
        $defaulters = $children->map(function (ChildProfile $child) use ($suggestions, $today) {
            $suggestion = $suggestions->suggestNextDose($child);

            if ($suggestion['status'] !== 'overdue' || $suggestion['due_at'] === null) {
                return null;
            }

            return [
                'child' => $child,
                'suggestion' => $suggestion,
                'days_overdue' => (int) $suggestion['due_at']->diffInDays($today),
            ];
        })
            ->filter(fn ($item) => $item !== null && $item['days_overdue'] >= $threshold)
            ->sortByDesc('days_overdue')
            ->values();

        return view('livewire.defaulter-page', [
            'threshold' => $threshold,
            'defaulters' => $defaulters,
        ])->layout('layouts.app', ['title' => 'Defaulters']);
    }
}
