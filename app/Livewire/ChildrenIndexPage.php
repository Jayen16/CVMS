<?php

namespace App\Livewire;

use App\Models\ChildProfile;
use App\Models\VaccineType;
use App\Services\VaccineScheduleVersionResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class ChildrenIndexPage extends Component
{
    public function render(VaccineScheduleVersionResolver $scheduleVersions): View
    {
        abort_unless(auth()->user()->canViewChildrenRegistry(), 403);

        $vaccineTypeId = request()->string('vaccine_type_id')->toString() ?: null;
        $children = $this->visibleChildren()
            ->with(['barangay', 'vaccinations.vaccineType', 'seriesVersions.scheduleVersion'])
            ->withCount([
                'vaccinations as completed_doses_count' => fn (Builder $query) => $query
                    ->where('verification_status', 'verified')
                    ->when($vaccineTypeId, fn (Builder $records) => $records->where('vaccine_type_id', $vaccineTypeId)),
            ])
            ->when(
                $vaccineTypeId && ! auth()->user()->isParent(),
                fn (Builder $query) => $query->whereHas('vaccinations', fn (Builder $records) => $records
                    ->where('vaccine_type_id', $vaccineTypeId)
                    ->where('verification_status', 'verified'))
            )
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $children->setCollection(
            $children->getCollection()->map(function (ChildProfile $child) use ($scheduleVersions, $vaccineTypeId) {
                $scheduleRows = $scheduleVersions->scheduleRowsForChild($child);

                $child->total_doses_count = $scheduleRows
                    ->when(
                        $vaccineTypeId !== null,
                        fn ($rows) => $rows->filter(fn ($doses) => $doses->first()?->vaccine_type_id === $vaccineTypeId)
                    )
                    ->sum(fn ($doses) => $doses->count());

                return $child;
            })
        );

        return view('children.index', [
            'children' => $children,
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
