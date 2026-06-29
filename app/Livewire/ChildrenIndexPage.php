<?php

namespace App\Livewire;

use App\Models\ChildProfile;
use App\Models\VaccineType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class ChildrenIndexPage extends Component
{
    public function render(): View
    {
        abort_unless(auth()->user()->canViewChildrenRegistry(), 403);

        $vaccineTypeId = request()->integer('vaccine_type_id') ?: null;

        return view('children.index', [
            'children' => $this->visibleChildren()
                ->with(['barangay', 'vaccinations'])
                ->when(
                    $vaccineTypeId && ! auth()->user()->isParent(),
                    fn (Builder $query) => $query->whereHas('vaccinations', fn (Builder $records) => $records
                        ->where('vaccine_type_id', $vaccineTypeId)
                        ->where('verification_status', 'verified'))
                )
                ->latest()
                ->paginate(12)
                ->withQueryString(),
            'vaccines' => VaccineType::where('active', true)->orderBy('name')->get(),
            'selectedVaccineTypeId' => $vaccineTypeId,
        ])->layout('layouts.app', [
            'title' => 'Children',
        ]);
    }

    /**
     * @return Builder<ChildProfile>
     */
    private function visibleChildren(): Builder
    {
        $query = ChildProfile::query();

        if (auth()->user()->isNurse()) {
            $query->where('barangay_id', auth()->user()->barangay_id);
        }

        if (auth()->user()->isBarangayAdmin()) {
            $query->where('barangay_id', auth()->user()->barangay_id);
        }

        if (auth()->user()->isParent()) {
            $query->whereHas('parents', fn (Builder $builder) => $builder->whereKey(auth()->id()));
        }

        return $query;
    }
}
