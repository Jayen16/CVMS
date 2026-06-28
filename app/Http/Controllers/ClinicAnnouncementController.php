<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\ClinicAnnouncement;
use App\Services\OfflineSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClinicAnnouncementController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $query = ClinicAnnouncement::query()
            ->with(['barangay', 'creator'])
            ->when($user->isParent(), fn ($builder) => $builder->whereIn('audience', ['all', 'parents']))
            ->when($user->isNurse(), fn ($builder) => $builder->whereIn('audience', ['all', 'staff']))
            ->latest('starts_on');

        if ($user->isNurse()) {
            $query->where(function ($builder) use ($user) {
                $builder->whereNull('barangay_id')
                    ->orWhere('barangay_id', $user->barangay_id);
            });
        }

        return view('announcements.index', [
            'announcements' => $query->paginate(12),
            'barangays' => Barangay::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, OfflineSyncService $offlineSync): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isNurse(), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:schedule,closure,campaign,stock'],
            'audience' => ['required', 'in:all,parents,staff'],
            'barangay_id' => ['nullable', 'exists:barangays,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'location' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        if (auth()->user()->isNurse()) {
            $validated['barangay_id'] = auth()->user()->barangay_id;
        }

        $announcement = ClinicAnnouncement::create([
            ...$validated,
            'created_by' => auth()->id(),
            'active' => true,
        ]);
        $offlineSync->queueUpsert($announcement->load(['barangay', 'creator']));

        return to_route('announcements.index')->with('status', 'Clinic announcement posted.');
    }

    public function toggle(ClinicAnnouncement $announcement, OfflineSyncService $offlineSync): RedirectResponse
    {
        $this->authorizeManage($announcement);

        $announcement->update(['active' => ! $announcement->active]);
        $offlineSync->queueUpsert($announcement->fresh(['barangay', 'creator']));

        return to_route('announcements.index')->with('status', 'Announcement status updated.');
    }

    public function destroy(ClinicAnnouncement $announcement, OfflineSyncService $offlineSync): RedirectResponse
    {
        $this->authorizeManage($announcement);

        $offlineSync->queueDelete($announcement);
        $announcement->delete();

        return to_route('announcements.index')->with('status', 'Announcement removed.');
    }

    private function authorizeManage(ClinicAnnouncement $announcement): void
    {
        $user = auth()->user();

        abort_unless($user->isAdmin() || $user->isNurse(), 403);
        abort_if($user->isNurse() && $announcement->barangay_id !== $user->barangay_id, 403);
    }
}
