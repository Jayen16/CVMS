<?php

namespace App\Livewire;

use App\Models\VaccineType;
use App\Models\VaccineScheduleVersion;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class VaccineSchedulesPage extends Component
{
    public function render(): View
    {
        $this->authorizeAdmin();

        $versions = VaccineScheduleVersion::query()
            ->orderByRaw("case when status = 'active' then 0 when status = 'draft' then 1 else 2 end")
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get();
        $selectedVersionId = request()->string('version')->toString() ?: $versions->firstWhere('status', 'active')?->id;

        return view('vaccine-schedules.index', [
            'versions' => $versions,
            'selectedVersionId' => $selectedVersionId,
            'vaccines' => VaccineType::query()
                ->with(['schedules' => fn ($query) => $query
                    ->where('vaccine_schedule_version_id', $selectedVersionId)
                    ->orderBy('dose_number')])
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
