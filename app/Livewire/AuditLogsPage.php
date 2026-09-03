<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogsPage extends Component
{
    use WithPagination;

    public string $search = '';

    public string $event = 'all';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $regionId = 'all';

    public string $provinceId = 'all';

    public string $municipalityId = 'all';

    public string $barangayId = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedEvent(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedRegionId(): void
    {
        $this->provinceId = 'all';
        $this->municipalityId = 'all';
        $this->barangayId = 'all';
        $this->resetPage();
    }

    public function updatedProvinceId(): void
    {
        $this->municipalityId = 'all';
        $this->barangayId = 'all';
        $this->resetPage();
    }

    public function updatedMunicipalityId(): void
    {
        $this->barangayId = 'all';
        $this->resetPage();
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->canViewOversight(), 403);

        $user = auth()->user();
        $accessibleBarangayIds = $user->accessibleBarangayIds();
        $reportBarangayIds = $accessibleBarangayIds;
        if ($user->isSuperAdmin() && $this->regionId !== 'all') {
            $reportBarangayIds = Barangay::whereIn('id', $reportBarangayIds)->whereHas('municipalityRelation.province', fn ($query) => $query->where('region_id', $this->regionId))->pluck('id');
        }
        if ($user->isSuperAdmin() && $this->provinceId !== 'all') {
            $reportBarangayIds = Barangay::whereIn('id', $reportBarangayIds)->whereHas('municipalityRelation', fn ($query) => $query->where('province_id', $this->provinceId))->pluck('id');
        }
        if ($user->isSuperAdmin() && $this->municipalityId !== 'all') {
            $reportBarangayIds = Barangay::whereIn('id', $reportBarangayIds)->where('municipality_id', $this->municipalityId)->pluck('id');
        }
        if ($this->barangayId !== 'all') {
            $reportBarangayIds = $reportBarangayIds->intersect([$this->barangayId])->values();
        }

        $logs = AuditLog::query()
            ->with('user')
            ->when(! $user->isSuperAdmin() || $this->regionId !== 'all' || $this->provinceId !== 'all' || $this->municipalityId !== 'all' || $this->barangayId !== 'all', fn ($query) => $query->whereHas('user', fn ($actor) => $actor->whereIn('barangay_id', $reportBarangayIds)))
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('description', 'like', '%'.$this->search.'%')
                        ->orWhere('auditable_type', 'like', '%'.$this->search.'%')
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->event !== 'all', fn ($query) => $query->where('event', $this->event))
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->paginate(20);

        return view('audit-logs.index', [
            'logs' => $logs,
            'regions' => Region::query()->orderBy('name')->get(),
            'provinces' => $user->isSuperAdmin() && $this->regionId !== 'all'
                ? Province::query()->where('region_id', $this->regionId)->orderBy('name')->get()
                : collect(),
            'municipalities' => $user->isSuperAdmin() && $this->provinceId !== 'all'
                ? Municipality::query()->where('province_id', $this->provinceId)->orderBy('name')->get()
                : collect(),
            'barangays' => ($user->isSuperAdmin() && $this->municipalityId !== 'all') || $user->isMunicipalAdmin()
                ? Barangay::query()->whereIn('id', $accessibleBarangayIds)->when($this->municipalityId !== 'all', fn ($query) => $query->where('municipality_id', $this->municipalityId))->orderBy('name')->get()
                : collect(),
        ])->layout('layouts.app', ['title' => 'Audit Logs']);
    }
}
