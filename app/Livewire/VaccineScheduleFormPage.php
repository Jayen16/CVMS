<?php

namespace App\Livewire;

use App\Models\VaccineSchedule;
use App\Models\VaccineType;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class VaccineScheduleFormPage extends Component
{
    public ?VaccineSchedule $vaccineSchedule = null;

    public function mount(?VaccineSchedule $vaccineSchedule = null): void
    {
        $this->vaccineSchedule = $vaccineSchedule;
    }

    public function render(): View
    {
        $this->authorizeAdmin();

        $schedule = $this->vaccineSchedule ?? new VaccineSchedule(['active' => true]);

        return view('vaccine-schedules.form', [
            'schedule' => $schedule,
            'vaccines' => VaccineType::where('active', true)->orderBy('name')->get(),
            'indications' => VaccineSchedule::indicationOptions(),
            'allowNewVaccine' => ! $schedule->exists,
        ])->layout('layouts.app', [
            'title' => $schedule->exists ? 'Edit Schedule' : 'Add Schedule',
        ]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
    }
}
