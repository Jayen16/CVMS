<?php

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\VaccinationReminder;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Reminder History')]
class ReminderHistoryPage extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $channel = 'all';

    #[Url]
    public string $regionId = 'all';

    #[Url]
    public string $provinceId = 'all';

    #[Url]
    public string $municipalityId = 'all';

    #[Url]
    public string $barangayId = 'all';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public int $perPage = 15;

    public function updating($name): void
    {
        if (in_array($name, ['search', 'status', 'channel', 'regionId', 'provinceId', 'municipalityId', 'barangayId', 'from', 'to'], true)) {
            $this->resetPage();
        }
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

    public function updatedPerPage(): void
    {
        $this->perPage = in_array($this->perPage, [10, 15, 25, 50], true) ? $this->perPage : 15;
        $this->resetPage();
    }

    public function render(): View
    {
        $user = auth()->user();
        abort_unless($user->canViewDefaulters(), 403);

        $accessibleBarangays = $user->accessibleBarangayIds();
        $filteredBarangays = $this->filteredBarangayIds($user, $accessibleBarangays);
        $query = VaccinationReminder::query()
            ->with(['child.barangay', 'parent'])
            ->whereHas('child', fn ($child) => $child->whereIn('barangay_id', $filteredBarangays))
            ->when($this->search !== '', function ($builder): void {
                $term = '%'.trim($this->search).'%';
                $builder->where(function ($reminders) use ($term): void {
                    $reminders
                        ->where('vaccine_name', 'like', $term)
                        ->orWhere('recipient', 'like', $term)
                        ->orWhereHas('child', fn ($child) => $child->where(function ($profile) use ($term): void {
                            $profile->where('first_name', 'like', $term)
                                ->orWhere('last_name', 'like', $term);
                        }))
                        ->orWhereHas('parent', fn ($parent) => $parent->where('name', 'like', $term));
                });
            })
            ->when($this->status !== 'all', fn ($builder) => $builder->where('status', $this->status))
            ->when($this->channel !== 'all', fn ($builder) => $builder->where('channel', $this->channel))
            ->when(($user->isSuperAdmin() || $user->isMunicipalAdmin()) && $this->barangayId !== 'all', fn ($builder) => $builder->whereHas('child', fn ($child) => $child->where('barangay_id', $this->barangayId)))
            ->when($this->from !== '', fn ($builder) => $builder->whereDate('created_at', '>=', $this->from))
            ->when($this->to !== '', fn ($builder) => $builder->whereDate('created_at', '<=', $this->to));

        return view('livewire.reminder-history-page', [
            'reminders' => $query->latest('created_at')->paginate($this->perPage),
        ])->layout('layouts.app', ['title' => 'Reminder History']);
    }

    private function filteredBarangayIds($user, $accessibleBarangays)
    {
        if (! $user->isSuperAdmin()) {
            if ($user->isMunicipalAdmin() && $this->barangayId !== 'all') {
                return $accessibleBarangays->intersect([$this->barangayId])->values();
            }

            return $accessibleBarangays;
        }

        return Barangay::query()
            ->whereIn('id', $accessibleBarangays)
            ->when($this->regionId !== 'all', fn ($builder) => $builder->whereHas('municipalityRelation.province', fn ($province) => $province->where('region_id', $this->regionId)))
            ->when($this->provinceId !== 'all', fn ($builder) => $builder->whereHas('municipalityRelation', fn ($municipality) => $municipality->where('province_id', $this->provinceId)))
            ->when($this->municipalityId !== 'all', fn ($builder) => $builder->where('municipality_id', $this->municipalityId))
            ->when($this->barangayId !== 'all', fn ($builder) => $builder->whereKey($this->barangayId))
            ->pluck('id');
    }
}
