<?php

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Services\ImmunizationSuggestionService;
use App\Services\PredictiveAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ImmunizationSchedulePage extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $status = 'all';
    #[Url] public string $risk = 'all';
    #[Url] public string $regionId = 'all';
    #[Url] public string $provinceId = 'all';
    #[Url] public string $municipalityId = 'all';
    #[Url] public string $barangayId = 'all';
    #[Url] public int $perPage = 10;

    public function updating($name): void
    {
        if (in_array($name, ['search', 'status', 'risk', 'regionId', 'provinceId', 'municipalityId', 'barangayId'], true)) {
            $this->resetPage();
        }
    }

    public function updatedPerPage(): void
    {
        $this->perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;
        $this->resetPage();
    }

    public function updatedRegionId(): void
    {
        $this->provinceId = 'all';
        $this->municipalityId = 'all';
        $this->barangayId = 'all';
    }

    public function updatedProvinceId(): void
    {
        $this->municipalityId = 'all';
        $this->barangayId = 'all';
    }

    public function updatedMunicipalityId(): void
    {
        $this->barangayId = 'all';
    }

    public function render(ImmunizationSuggestionService $suggestions, PredictiveAnalyticsService $analytics): View
    {
        abort_unless(auth()->user()->canViewDefaulters(), 403);

        $barangayIds = $this->filteredBarangayIds(auth()->user());
        $children = ChildProfile::query()->visibleTo(auth()->user())
            ->whereIn('barangay_id', $barangayIds)
            ->with(['barangay', 'parents', 'vaccinations'])->withCount('vaccinations')
            ->when(trim($this->search) !== '', function ($query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(fn ($child) => $child->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term));
            })->get();
        $risks = $analytics->missedDoseRisk(auth()->user(), $this->regionId, $this->provinceId, $this->municipalityId, $this->barangayId)->keyBy(fn (array $row) => $row['child']->id);
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

        $totalMatching = $rows->count();
        $rows = new LengthAwarePaginator(
            $rows->forPage($this->getPage(), $this->perPage)->values(),
            $totalMatching,
            $this->perPage,
            $this->getPage(),
            ['path' => request()->url(), 'pageName' => 'page'],
        );

        return view('livewire.immunization-schedule-page', [
            'rows' => $rows,
            'totalChildren' => $children->count(),
            'totalMatching' => $totalMatching,
            'regions' => auth()->user()->isSuperAdmin() ? Region::query()->orderBy('name')->get() : collect(),
            'provinces' => auth()->user()->isSuperAdmin() ? Province::query()->when($this->regionId !== 'all', fn ($query) => $query->where('region_id', $this->regionId))->orderBy('name')->get() : collect(),
            'municipalities' => auth()->user()->isSuperAdmin() ? Municipality::query()
                ->when($this->regionId !== 'all', fn ($query) => $query->whereHas('province', fn ($province) => $province->where('region_id', $this->regionId)))
                ->when($this->provinceId !== 'all', fn ($query) => $query->where('province_id', $this->provinceId))
                ->orderBy('name')->get() : collect(),
            'barangays' => Barangay::query()->whereIn('id', auth()->user()->accessibleBarangayIds())
                ->when(auth()->user()->isSuperAdmin() && $this->municipalityId !== 'all', fn ($query) => $query->where('municipality_id', $this->municipalityId))
                ->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => 'Schedule monitoring']);
    }

    /** @return \Illuminate\Support\Collection<int, string> */
    private function filteredBarangayIds($user)
    {
        $query = Barangay::query()->whereIn('id', $user->accessibleBarangayIds());
        if ($this->regionId !== 'all') {
            $query->whereHas('municipalityRelation.province', fn ($location) => $location->where('region_id', $this->regionId));
        }
        if ($this->provinceId !== 'all') {
            $query->whereHas('municipalityRelation', fn ($location) => $location->where('province_id', $this->provinceId));
        }
        if ($this->municipalityId !== 'all') {
            $query->where('municipality_id', $this->municipalityId);
        }
        if ($this->barangayId !== 'all') {
            $query->whereKey($this->barangayId);
        }
        return $query->pluck('id');
    }

    private function contactChannel(ChildProfile $child): string
    {
        if ($child->parents->contains(fn ($parent) => filled($parent->phone)) || filled($child->guardian_contact)) return 'SMS priority';
        if ($child->parents->contains(fn ($parent) => filled($parent->email))) return 'Email';
        return 'No contact';
    }
}
