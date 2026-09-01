<?php

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\ClinicAnnouncement;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Services\InAppNotificationService;
use App\Services\OfflineSyncService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Clinic Announcements')]
class AnnouncementsPage extends Component
{
    use WithPagination;

    public string $title = '';

    public string $category = 'schedule';

    public string $audience = 'all';

    public string $barangay_id = 'all';

    public string $region_id = 'all';

    public string $province_id = 'all';

    public string $municipality_id = 'all';

    public string $starts_on = '';

    public string $ends_on = '';

    public string $location = '';

    public string $message = '';

    public bool $viewAll = false;

    public int $perPage = 15;

    public string $dateFilter = '';

    public function mount(): void
    {
        $this->starts_on = now()->toDateString();
        $this->viewAll = request()->routeIs('announcements.all');
        foreach (['region_id', 'province_id', 'municipality_id', 'barangay_id'] as $field) {
            $this->{$field} = (string) request()->query($field, $this->{$field});
        }
    }

    public function updatedRegionId(): void
    {
        $this->province_id = 'all';
        $this->municipality_id = 'all';
        $this->barangay_id = 'all';
        $this->resetPage();
    }

    public function updatedProvinceId(): void
    {
        $this->municipality_id = 'all';
        $this->barangay_id = 'all';
        $this->resetPage();
    }

    public function updatedMunicipalityId(): void
    {
        $this->barangay_id = 'all';
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

    public function save(OfflineSyncService $offlineSync, InAppNotificationService $notifications): void
    {
        abort_unless(auth()->user()->canManageAnnouncements(), 403);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:schedule,closure,campaign,stock'],
            'audience' => ['required', 'in:all,parents,staff'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'location' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        if (! auth()->user()->isSuperAdmin()) {
            $validated['barangay_id'] = auth()->user()->barangay_id;
        } else {
            $validated['region_id'] = $this->region_id !== 'all' ? $this->region_id : null;
            $validated['province_id'] = $this->province_id !== 'all' ? $this->province_id : null;
            $validated['municipality_id'] = $this->municipality_id !== 'all' ? $this->municipality_id : null;
            $validated['barangay_id'] = $this->barangay_id !== 'all' ? $this->barangay_id : null;
        }

        $announcement = ClinicAnnouncement::create([
            ...$validated,
            'created_by' => auth()->id(),
            'active' => true,
        ]);

        $offlineSync->queueUpsert($announcement->load(['barangay', 'creator']));
        $notifications->announcementPublished($announcement);

        $this->reset('title', 'location', 'message', 'ends_on');
        $this->category = 'schedule';
        $this->audience = 'all';
        $this->starts_on = now()->toDateString();

        Flux::toast(variant: 'success', text: 'Clinic announcement posted.');
    }

    public function toggle(string $announcementId, OfflineSyncService $offlineSync): void
    {
        $announcement = ClinicAnnouncement::findOrFail($announcementId);
        $this->authorizeManage($announcement);

        $announcement->update(['active' => ! $announcement->active]);
        $offlineSync->queueUpsert($announcement->fresh(['barangay', 'creator']));

        Flux::toast(variant: 'success', text: 'Announcement status updated.');
    }

    public function remove(string $announcementId, OfflineSyncService $offlineSync): void
    {
        $announcement = ClinicAnnouncement::findOrFail($announcementId);
        $this->authorizeManage($announcement);

        $offlineSync->queueDelete($announcement);
        $announcement->delete();

        Flux::toast(variant: 'success', text: 'Announcement removed.');
    }

    public function render(): View
    {
        $user = auth()->user();

        $announcements = ClinicAnnouncement::query()
            ->with(['barangay', 'creator', 'region', 'province', 'municipality'])
            ->when($user->isParent(), fn ($builder) => $builder->whereIn('audience', ['all', 'parents']))
            ->when($user->isNurse() || $user->isBarangayAdmin(), fn ($builder) => $builder->whereIn('audience', ['all', 'staff']))
            ->visibleTo($user)
            ->inLocation($this->region_id !== 'all' ? $this->region_id : null, $this->province_id !== 'all' ? $this->province_id : null, $this->municipality_id !== 'all' ? $this->municipality_id : null, $this->barangay_id !== 'all' ? $this->barangay_id : null)
            ->when($this->viewAll && filled($this->dateFilter), fn ($builder) => $builder->whereDate('starts_on', $this->dateFilter))
            ->latest('starts_on')
            ->when($this->viewAll, fn ($builder) => $builder->paginate($this->perPage), fn ($builder) => $builder->take(10)->get());

        return view('livewire.announcements-page', [
            'announcements' => $announcements,
            'regions' => Region::query()->orderBy('name')->get(),
            'provinces' => $user->isSuperAdmin() && $this->region_id !== 'all'
                ? Province::query()->where('region_id', $this->region_id)->orderBy('name')->get()
                : collect(),
            'municipalities' => $user->isSuperAdmin() && $this->province_id !== 'all'
                ? Municipality::query()->where('province_id', $this->province_id)->orderBy('name')->get()
                : collect(),
            'barangays' => Barangay::query()
                ->when($user->isSuperAdmin(), function ($query) {
                    $query
                        ->when($this->municipality_id !== 'all', fn ($builder) => $builder->where('municipality_id', $this->municipality_id))
                        ->when($this->province_id !== 'all', fn ($builder) => $builder->whereHas('municipalityRelation', fn ($municipality) => $municipality->where('province_id', $this->province_id)))
                        ->when($this->region_id !== 'all', fn ($builder) => $builder->whereHas('municipalityRelation.province', fn ($province) => $province->where('region_id', $this->region_id)));
                }, fn ($query) => $query->whereIn('id', $user->accessibleBarangayIds()))
                ->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => 'Clinic Announcements']);
    }

    private function authorizeManage(ClinicAnnouncement $announcement): void
    {
        $user = auth()->user();

        abort_unless($user->canManageAnnouncements(), 403);
        abort_if(! $user->isSuperAdmin() && $announcement->barangay_id !== $user->barangay_id, 403);
    }
}
