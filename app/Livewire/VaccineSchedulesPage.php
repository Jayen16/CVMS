<?php

namespace App\Livewire;

use App\Models\VaccineType;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class VaccineSchedulesPage extends Component
{
    public function render(): View
    {
        $this->authorizeAdmin();

        return view('vaccine-schedules.index', [
            'vaccines' => VaccineType::query()
                ->with(['schedules' => fn ($query) => $query->orderBy('dose_number')])
                ->orderBy('name')
                ->get(),
        ])->layout('layouts.app', [
            'title' => 'Vaccine Schedules',
        ]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
    }
}
