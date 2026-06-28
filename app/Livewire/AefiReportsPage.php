<?php

namespace App\Livewire;

use App\Models\AdverseEventReport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('AEFI Reports')]
class AefiReportsPage extends Component
{
    use WithPagination;

    public function render(): View
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isNurse(), 403);

        $reports = AdverseEventReport::query()
            ->with(['child.barangay', 'vaccineType', 'reporter'])
            ->when(auth()->user()->isNurse(), fn ($query) => $query->whereHas('child', fn ($child) => $child->where('barangay_id', auth()->user()->barangay_id)))
            ->latest('event_date')
            ->paginate(15);

        return view('livewire.aefi-reports-page', [
            'reports' => $reports,
        ])->layout('layouts.app', ['title' => 'AEFI Reports']);
    }
}
