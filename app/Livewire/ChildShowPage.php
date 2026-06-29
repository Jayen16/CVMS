<?php

namespace App\Livewire;

use App\Models\ChildProfile;
use App\Models\VaccinationRecord;
use App\Models\VaccineType;
use App\Services\ImmunizationSuggestionService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ChildShowPage extends Component
{
    public ChildProfile $child;

    public function mount(ChildProfile $child): void
    {
        $this->child = $child;
    }

    public function render(ImmunizationSuggestionService $suggestions): View
    {
        abort_unless(auth()->user()->canViewChildrenRegistry(), 403);
        $this->authorizeChild($this->child);

        $this->child->load([
            'barangay',
            'parents',
            'vaccinations.vaccineType',
            'vaccinations.recorder',
            'vaccinations.submitter',
            'vaccinations.verifier',
            'adverseEventReports.vaccineType',
            'adverseEventReports.reporter',
        ]);

        $editableRecord = null;

        if (auth()->user()->isParent() && request()->filled('edit_record')) {
            $editableRecord = $this->child->vaccinations
                ->first(fn (VaccinationRecord $record) => $record->id === request()->integer('edit_record'));

            abort_if($editableRecord === null, 404);
            abort_if($editableRecord->submitted_by !== auth()->id(), 403);
            abort_if(! $editableRecord->isPendingVerification(), 403);
        }

        return view('children.show', [
            'child' => $this->child,
            'suggestion' => $suggestions->suggestNextDose($this->child),
            'vaccines' => VaccineType::where('active', true)->orderBy('name')->get(),
            'editableRecord' => $editableRecord,
        ])->layout('layouts.app', [
            'title' => $this->child->full_name,
        ]);
    }

    private function authorizeChild(ChildProfile $child): void
    {
        abort_if(auth()->user()->isNurse() && $child->barangay_id !== auth()->user()->barangay_id, 403);
        abort_if(auth()->user()->isBarangayAdmin() && $child->barangay_id !== auth()->user()->barangay_id, 403);
        abort_if(auth()->user()->isParent() && ! $child->parents()->whereKey(auth()->id())->exists(), 403);
    }
}
