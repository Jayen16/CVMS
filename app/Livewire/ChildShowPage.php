<?php

namespace App\Livewire;

use App\Models\ChildProfile;
use App\Models\VaccinationRecord;
use App\Models\VaccineInventoryItem;
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
            'adverseEventReports.vaccinationRecord.vaccineType',
            'adverseEventReports.reporter',
        ]);

        $editableRecord = null;

        if (auth()->user()->isParent() && request()->filled('edit_record')) {
            $editableRecord = $this->child->vaccinations
                ->first(fn (VaccinationRecord $record) => $record->id === request()->string('edit_record')->toString());

            abort_if($editableRecord === null, 404);
            abort_if($editableRecord->submitted_by !== auth()->id(), 403);
            abort_if(! $editableRecord->isParentEditable(), 403);
        }

        return view('children.show', [
            'child' => $this->child,
            'suggestion' => $suggestions->suggestNextDose($this->child),
            'vaccines' => VaccineType::where('active', true)->orderBy('name')->get(),
            'inventoryItems' => VaccineInventoryItem::query()
                ->where('barangay_id', $this->child->barangay_id)
                ->with('vaccineType')
                ->withSum(['transactions as stock_in' => fn ($query) => $query->where('movement', 'in')], 'quantity')
                ->withSum(['transactions as stock_out' => fn ($query) => $query->where('movement', 'out')], 'quantity')
                ->orderBy('item_code')
                ->get()
                ->filter(fn (VaccineInventoryItem $item): bool => $item->availableStock() > 0),
            'editableRecord' => $editableRecord,
        ])->layout('layouts.app', [
            'title' => $this->child->full_name,
        ]);
    }

    private function authorizeChild(ChildProfile $child): void
    {
        abort_if(auth()->user()->isMunicipalAdmin() && ! auth()->user()->canAccessBarangay($child->barangay_id), 403);
        abort_if(auth()->user()->isNurse() && $child->barangay_id !== auth()->user()->barangay_id, 403);
        abort_if(auth()->user()->isBarangayAdmin() && $child->barangay_id !== auth()->user()->barangay_id, 403);
        abort_if(auth()->user()->isParent() && ! $child->parents()->whereKey(auth()->id())->exists(), 403);
    }
}
