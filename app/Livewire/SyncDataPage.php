<?php

namespace App\Livewire;

use App\Models\AdverseEventReport;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\ClinicAnnouncement;
use App\Models\Municipality;
use App\Models\OfflineSyncOutbox;
use App\Models\Province;
use App\Models\Region;
use App\Models\SyncStatus;
use App\Models\SystemInstallation;
use App\Models\User;
use App\Models\VaccinationRecord;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class SyncDataPage extends Component
{
    use WithPagination;

    public bool $viewAll = false;

    public bool $viewProcessedAll = false;

    public int $perPage = 15;

    public string $dateFilter = '';

    public string $regionId = 'all';

    public string $provinceId = 'all';

    public string $municipalityId = 'all';

    public string $barangayId = 'all';

    public function mount(): void
    {
        $this->viewAll = request()->routeIs('sync.all');
        $this->viewProcessedAll = request()->routeIs('sync.processed');
        foreach (['regionId' => 'region_id', 'provinceId' => 'province_id', 'municipalityId' => 'municipality_id', 'barangayId' => 'barangay_id'] as $property => $queryKey) {
            $this->{$property} = (string) request()->query($queryKey, $this->{$property});
        }
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

    public function updatedBarangayId(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = in_array($this->perPage, [10, 15, 25, 50], true) ? $this->perPage : 15;
        $this->resetPage();
    }

    public function updatedDateFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);

        $latestStatus = SyncStatus::query()
            ->with('user')
            ->where('scope', 'global')
            ->first();
        $installation = config('system.instance_type') === 'facility'
            ? SystemInstallation::query()->latest('created_at')->first()
            : null;

        $locationData = $this->locationData($user);
        $rowsQuery = $this->locationScopedQuery(OfflineSyncOutbox::query(), $locationData['barangayIds'], $locationData['syncUuids'])
            ->when($this->viewAll && filled($this->dateFilter), fn ($query) => $query->whereDate('queued_at', $this->dateFilter));

        $recentRows = $this->viewProcessedAll
            ? collect()
            : ($this->viewAll
            ? $rowsQuery->latest('queued_at')->paginate($this->perPage)
            : $rowsQuery->latest('queued_at')->take(10)->get());
        $recentRows = $this->attachLocations($recentRows);
        $processedQuery = $this->locationScopedQuery(
            OfflineSyncOutbox::query(),
            $locationData['barangayIds'],
            $locationData['syncUuids'],
        )->where('status', 'synced');
        if ($this->viewProcessedAll && filled($this->dateFilter)) {
            $processedQuery->whereDate('synced_at', $this->dateFilter);
        }

        $lastProcessedRows = collect();
        if ($latestStatus?->last_attempted_at && $latestStatus->last_synced_at) {
            $lastProcessedRows = (clone $processedQuery)
                ->whereBetween('synced_at', [$latestStatus->last_attempted_at, $latestStatus->last_synced_at])
                ->latest('synced_at')
                ->get();
        }
        $processedRows = $this->viewProcessedAll
            ? $processedQuery->latest('synced_at')->paginate($this->perPage)
            : collect();

        return view('livewire.sync-data-page', [
            'latestStatus' => $latestStatus,
            'installation' => $installation,
            'pendingCount' => config('offline.enabled')
                ? (clone $rowsQuery)->whereIn('status', ['pending', 'processing'])->count()
                : 0,
            'failedCount' => config('offline.enabled')
                ? (clone $rowsQuery)->where('status', 'failed')->count()
                : 0,
            'recentRows' => $recentRows,
            'lastProcessedRows' => $lastProcessedRows,
            'processedRows' => $processedRows,
            ...$locationData,
        ])->layout('layouts.app', [
            'title' => 'Sync Data',
        ]);
    }

    /** @return array<string, mixed> */
    private function locationData(User $user): array
    {
        $regionSelected = $user->isSuperAdmin() && $this->regionId !== 'all';
        $provinceSelected = $regionSelected && $this->provinceId !== 'all';
        $municipalitySelected = $regionSelected && $this->municipalityId !== 'all';
        $accessibleIds = $user->accessibleBarangayIds();

        $barangayIds = $accessibleIds;
        if ($regionSelected) {
            $barangayIds = Barangay::whereIn('id', $barangayIds)
                ->whereHas('municipalityRelation.province', fn ($query) => $query->where('region_id', $this->regionId))
                ->pluck('id');
        }
        if ($provinceSelected) {
            $barangayIds = Barangay::whereIn('id', $barangayIds)
                ->whereHas('municipalityRelation', fn ($query) => $query->where('province_id', $this->provinceId))
                ->pluck('id');
        }
        if ($municipalitySelected) {
            $barangayIds = Barangay::whereIn('id', $barangayIds)->where('municipality_id', $this->municipalityId)->pluck('id');
        }
        if ($this->barangayId !== 'all') {
            $barangayIds = $barangayIds->intersect([$this->barangayId])->values();
        }

        $allLocations = $user->isSuperAdmin() && ! $regionSelected && $this->barangayId === 'all';
        $syncUuids = $allLocations ? [] : $this->syncUuidsForBarangays($barangayIds);

        return [
            'regions' => $user->isSuperAdmin() ? Region::orderBy('name')->get() : collect(),
            'provinces' => $user->isSuperAdmin() && $regionSelected ? Province::where('region_id', $this->regionId)->orderBy('name')->get() : collect(),
            'municipalities' => $user->isSuperAdmin() && $regionSelected
                ? Municipality::whereHas('province', fn ($query) => $query->where('region_id', $this->regionId))
                    ->when($provinceSelected, fn ($query) => $query->where('province_id', $this->provinceId))
                    ->orderBy('name')->get()
                : collect(),
            'barangays' => $user->isMunicipalAdmin()
                ? Barangay::whereIn('id', $accessibleIds)->orderBy('name')->get()
                : ($user->isSuperAdmin() && $municipalitySelected ? Barangay::whereIn('id', $barangayIds)->orderBy('name')->get() : collect()),
            'regionFilter' => $this->regionId,
            'provinceFilter' => $this->provinceId,
            'municipalityFilter' => $this->municipalityId,
            'barangayFilter' => $this->barangayId,
            'barangayIds' => $barangayIds,
            'syncUuids' => $syncUuids,
        ];
    }

    /** @return array<int, string> */
    private function syncUuidsForBarangays($barangayIds): array
    {
        $childUuids = ChildProfile::whereIn('barangay_id', $barangayIds)->pluck('sync_uuid')->all();
        $announcementUuids = ClinicAnnouncement::whereIn('barangay_id', $barangayIds)->pluck('sync_uuid')->all();
        $recordUuids = VaccinationRecord::whereIn('child_profile_id', ChildProfile::whereIn('barangay_id', $barangayIds)->pluck('id'))->pluck('sync_uuid')->all();
        $aefiUuids = AdverseEventReport::whereIn('child_profile_id', ChildProfile::whereIn('barangay_id', $barangayIds)->pluck('id'))->pluck('sync_uuid')->all();

        return compact('childUuids', 'announcementUuids', 'recordUuids', 'aefiUuids');
    }

    private function locationScopedQuery($query, $barangayIds, array $syncUuids)
    {
        if ($syncUuids === []) {
            return $query;
        }

        return $query->where(function ($query) use ($syncUuids): void {
            $query->where(function ($query) use ($syncUuids): void {
                $query->where('model_type', ChildProfile::class)->whereIn('model_sync_uuid', $syncUuids['childUuids']);
            })->orWhere(function ($query) use ($syncUuids): void {
                $query->where('model_type', ClinicAnnouncement::class)->whereIn('model_sync_uuid', $syncUuids['announcementUuids']);
            })->orWhere(function ($query) use ($syncUuids): void {
                $query->where('model_type', VaccinationRecord::class)->whereIn('model_sync_uuid', $syncUuids['recordUuids']);
            })->orWhere(function ($query) use ($syncUuids): void {
                $query->where('model_type', AdverseEventReport::class)->whereIn('model_sync_uuid', $syncUuids['aefiUuids']);
            });
        });
    }

    private function attachLocations($rows)
    {
        foreach ($rows as $row) {
            $location = match ($row->model_type) {
                ChildProfile::class => ChildProfile::query()->with('barangay.municipalityRelation.province.region')->where('sync_uuid', $row->model_sync_uuid)->first()?->barangay,
                VaccinationRecord::class => VaccinationRecord::query()->with('child.barangay.municipalityRelation.province.region')->where('sync_uuid', $row->model_sync_uuid)->first()?->child?->barangay,
                ClinicAnnouncement::class => ClinicAnnouncement::query()->with('barangay.municipalityRelation.province.region')->where('sync_uuid', $row->model_sync_uuid)->first()?->barangay,
                AdverseEventReport::class => AdverseEventReport::query()->with('child.barangay.municipalityRelation.province.region')->where('sync_uuid', $row->model_sync_uuid)->first()?->child?->barangay,
                default => null,
            };

            $row->sync_location = $location === null ? null : [
                'region' => $location->municipalityRelation?->province?->region?->name,
                'province' => $location->municipalityRelation?->province?->name,
                'municipality' => $location->municipalityRelation?->name,
                'barangay' => $location->name,
            ];
        }

        return $rows;
    }
}
