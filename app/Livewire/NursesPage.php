<?php

namespace App\Livewire;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Component;

class NursesPage extends Component
{
    public function render(): View
    {
        $this->authorizeStaffManager();

        $user = auth()->user();
        $managesBarangayAdmins = $user->canManageBarangayAdmins();
        $managedRole = $managesBarangayAdmins ? 'barangay_admin' : 'nurse';

        return view('nurses.index', [
            'staff' => User::query()
                ->whereJsonContains('roles', $managedRole)
                ->when(
                    $user->canManageNurses(),
                    fn ($query) => $query->where('barangay_id', $user->barangay_id)
                )
                ->with('barangay')
                ->latest()
                ->paginate(12),
            'barangays' => Barangay::orderBy('name')->get(),
            'managedRole' => $managedRole,
        ])->layout('layouts.app', [
            'title' => $managedRole === 'barangay_admin' ? 'Barangay Admins' : 'Nurses',
        ]);
    }

    private function authorizeStaffManager(): void
    {
        abort_unless(auth()->user()->canManageBarangayStaff(), 403);
    }
}
