<?php

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\ClinicAnnouncement;
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

    public ?string $barangay_id = null;

    public string $starts_on = '';

    public string $ends_on = '';

    public string $location = '';

    public string $message = '';

    public function mount(): void
    {
        $this->starts_on = now()->toDateString();
    }

    public function save(OfflineSyncService $offlineSync, InAppNotificationService $notifications): void
    {
        abort_unless(auth()->user()->canManageAnnouncements(), 403);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:schedule,closure,campaign,stock'],
            'audience' => ['required', 'in:all,parents,staff'],
            'barangay_id' => ['nullable', 'exists:barangays,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'location' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        if (! auth()->user()->isSuperAdmin()) {
            $validated['barangay_id'] = auth()->user()->barangay_id;
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
            ->with(['barangay', 'creator'])
            ->when($user->isParent(), fn ($builder) => $builder->whereIn('audience', ['all', 'parents']))
            ->when($user->isNurse() || $user->isBarangayAdmin(), fn ($builder) => $builder->whereIn('audience', ['all', 'staff']))
            ->when($user->isNurse() || $user->isBarangayAdmin(), fn ($builder) => $builder->where(function ($query) use ($user) {
                $query->whereNull('barangay_id')->orWhere('barangay_id', $user->barangay_id);
            }))
            ->latest('starts_on')
            ->paginate(12);

        return view('livewire.announcements-page', [
            'announcements' => $announcements,
            'barangays' => Barangay::orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => 'Clinic Announcements']);
    }

    private function authorizeManage(ClinicAnnouncement $announcement): void
    {
        $user = auth()->user();

        abort_unless($user->canManageAnnouncements(), 403);
        abort_if(! $user->isSuperAdmin() && $announcement->barangay_id !== $user->barangay_id, 403);
    }
}
