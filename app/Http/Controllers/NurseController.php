<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NurseController extends Controller
{
    public function index(): View
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeStaffManager();

        $user = auth()->user();
        $managesBarangayAdmins = $user->canManageBarangayAdmins();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'barangay_id' => [$managesBarangayAdmins ? 'required' : 'nullable', 'exists:barangays,id'],
            'barangay_name' => ['nullable', 'string', 'max:255'],
            'assign_nurse_role' => ['nullable', 'boolean'],
        ]);

        $barangayId = $managesBarangayAdmins
            ? ($validated['barangay_id'] ?? null)
            : $user->barangay_id;

        if ($managesBarangayAdmins && ! $barangayId && filled($validated['barangay_name'] ?? null)) {
            $barangayId = Barangay::firstOrCreate(['name' => $validated['barangay_name']])->id;
        }

        $roles = $managesBarangayAdmins
            ? ['barangay_admin']
            : ['nurse'];

        if ($managesBarangayAdmins && filled($validated['assign_nurse_role'])) {
            $roles[] = 'nurse';
        }

        $staff = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Str::password(32),
            'role' => $roles[0],
            'roles' => $roles,
            'barangay_id' => $barangayId,
            'is_active' => false,
            'invitation_accepted_at' => null,
        ]);

        Password::sendResetLink(['email' => $staff->email]);

        return to_route('nurses.index')->with('status', $managesBarangayAdmins
            ? 'Barangay admin account created. A password setup link was sent by email.'
            : 'Nurse account created. A password setup link was sent by email.');
    }

    public function resendSetupLink(User $nurse): RedirectResponse
    {
        $this->authorizeStaffManager();
        $this->authorizeManagedUser($nurse);

        Password::sendResetLink(['email' => $nurse->email]);

        return to_route('nurses.index')->with('status', 'Password setup link sent again.');
    }

    public function toggle(User $nurse): RedirectResponse
    {
        $this->authorizeStaffManager();
        $this->authorizeManagedUser($nurse);

        abort_if($nurse->invitation_accepted_at === null, 422, 'Nurse must configure the account before status can be changed.');

        $nurse->update(['is_active' => ! $nurse->is_active]);

        return to_route('nurses.index')->with('status', 'Nurse status updated.');
    }

    public function destroy(User $nurse): RedirectResponse
    {
        $this->authorizeStaffManager();
        $this->authorizeManagedUser($nurse);

        $nurse->delete();

        return to_route('nurses.index')->with('status', 'Account removed.');
    }

    private function authorizeManagedUser(User $user): void
    {
        $manager = auth()->user();

        if ($manager->canManageBarangayAdmins()) {
            abort_unless($user->isBarangayAdmin(), 404);

            return;
        }

        abort_unless($manager->canManageNurses(), 403);
        abort_unless($user->isNurse(), 404);
        abort_if($user->isBarangayAdmin(), 404);
        abort_if($user->barangay_id !== $manager->barangay_id, 403);
    }

    private function authorizeStaffManager(): void
    {
        abort_unless(auth()->user()->canManageBarangayStaff(), 403);
    }
}
