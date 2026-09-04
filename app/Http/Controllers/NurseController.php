<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Municipality;
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
                ->when($user->isMunicipalAdmin(), fn ($query) => $query->where('municipality_id', $user->municipality_id))
                ->when($user->isBarangayAdmin(), fn ($query) => $query->where('barangay_id', $user->barangay_id))
                ->with('barangay')
                ->latest()
                ->paginate(12),
            'barangays' => Barangay::orderBy('name')->get(),
            'municipalities' => Municipality::with('province.region')->orderBy('name')->get(),
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
            'phone' => [$this->usesOfflineFacilitySetup() ? 'nullable' : 'required', 'string', 'max:32', Rule::unique('users', 'phone')],
            'barangay_id' => [$managesBarangayAdmins ? 'required' : 'nullable', 'exists:barangays,id'],
            'municipality_id' => ['nullable', 'exists:municipalities,id'],
            'barangay_name' => ['nullable', 'string', 'max:255'],
        ]);

        $barangayId = $managesBarangayAdmins
            ? ($validated['barangay_id'] ?? null)
            : ($user->isMunicipalAdmin() ? null : $user->barangay_id);

        $municipalityId = $user->isMunicipalAdmin()
            ? $user->municipality_id
            : Barangay::whereKey($barangayId)->value('municipality_id');

        if ($managesBarangayAdmins && ! $barangayId && filled($validated['barangay_name'] ?? null)) {
            $barangayId = Barangay::firstOrCreate([
                'name' => $validated['barangay_name'],
                'municipality_id' => $user->isMunicipalAdmin() ? $user->municipality_id : null,
            ])->id;
        }

        if ($managesBarangayAdmins) {
            abort_unless(
                $user->isSuperAdmin() || Barangay::whereKey($barangayId)->where('municipality_id', $user->municipality_id)->exists(),
                403,
                'Barangay must belong to your municipality.'
            );
        }

        $roles = $managesBarangayAdmins
            ? ['barangay_admin']
            : ['nurse'];

        $staff = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => User::normalizePhone($validated['phone'] ?? null),
            'password' => Str::password(32),
            'role' => $roles[0],
            'roles' => $roles,
            'permissions' => $roles === ['nurse'] ? User::defaultNursePermissions() : null,
            'barangay_id' => $barangayId,
            'municipality_id' => $municipalityId,
            'is_active' => false,
            'invitation_accepted_at' => null,
        ]);

        if ($this->usesOfflineFacilitySetup()) {
            return $this->setupLinkResponse(
                $staff,
                $managesBarangayAdmins
                    ? 'Barangay admin account created. Copy the local password setup link and open it on the user’s computer.'
                    : 'Nurse account created. Copy the local password setup link and open it on the nurse’s computer.',
                true,
            );
        }

        $status = Password::sendResetLink(['email' => $staff->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return to_route('nurses.index')->withErrors([
                'email' => __($status),
            ])->withInput();
        }

        return $this->setupLinkResponse(
            $staff,
            $managesBarangayAdmins
                ? 'Barangay admin account created. A password setup link was sent by email.'
                : 'Nurse account created. A password setup link was sent by email.'
        );
    }

    public function resendSetupLink(User $nurse): RedirectResponse
    {
        $this->authorizeStaffManager();
        $this->authorizeManagedUser($nurse);
        abort_if($nurse->isArchived(), 422, 'Archived accounts cannot receive setup links.');

        if ($this->usesOfflineFacilitySetup()) {
            return $this->setupLinkResponse(
                $nurse,
                'A local password setup link is ready. Copy it and open it on the nurse’s computer.',
                true,
            );
        }

        $status = Password::sendResetLink(['email' => $nurse->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return to_route('nurses.index')->withErrors([
                'email' => __($status),
            ]);
        }

        return $this->setupLinkResponse($nurse, 'Password setup link sent again.');
    }

    public function toggle(User $nurse): RedirectResponse
    {
        $this->authorizeStaffManager();
        $this->authorizeManagedUser($nurse);
        abort_if($nurse->isArchived(), 422, 'Archived accounts cannot be activated.');

        abort_if($nurse->invitation_accepted_at === null, 422, 'Nurse must configure the account before status can be changed.');

        $nurse->update(['is_active' => ! $nurse->is_active]);

        return to_route('nurses.index')->with('status', 'Nurse status updated.');
    }

    public function updatePermissions(Request $request, User $nurse): RedirectResponse
    {
        $this->authorizeStaffManager();
        abort_unless(auth()->user()->isBarangayAdmin(), 403);
        abort_unless($nurse->isNurse(), 404);
        abort_unless($nurse->barangay_id === auth()->user()->barangay_id, 403);
        abort_if($nurse->isArchived(), 422, 'Archived accounts cannot have permissions changed.');

        $definitions = User::nursePermissionDefinitions();
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys($definitions))],
        ]);

        $oldPermissions = $nurse->nursePermissions();
        // Hidden capabilities remain stored and enabled so they can be restored
        // in the nurse access UI without changing the permission model.
        $newPermissions = array_merge(
            $validated['permissions'] ?? [],
            array_intersect($oldPermissions, User::hiddenNursePermissionKeys()),
        );
        sort($oldPermissions);
        sort($newPermissions);

        $nurse->syncNursePermissions($newPermissions);
        $nurse->save();

        if ($oldPermissions !== $newPermissions) {
            AuditLog::recordAction(
                'permissions_updated',
                'Updated nurse permissions for '.$nurse->name,
                $nurse,
                ['permissions' => $newPermissions],
                ['permissions' => $oldPermissions],
            );
        }

        return to_route('nurses.index')->with('status', 'Nurse permissions updated.');
    }

    public function restore(User $nurse): RedirectResponse
    {
        $this->authorizeStaffManager();
        $this->authorizeManagedUser($nurse);

        $nurse->update([
            'archived_at' => null,
            'archived_by' => null,
            'archive_reason' => null,
            'is_active' => false,
        ]);

        AuditLog::recordAction('staff_restored', 'Restored staff account '.$nurse->name, $nurse);

        return to_route('nurses.index')->with('status', 'Account restored and set to inactive.');
    }

    public function destroy(User $nurse): RedirectResponse
    {
        $this->authorizeStaffManager();
        $this->authorizeManagedUser($nurse);

        $reason = request()->validate([
            'archive_reason' => ['required', 'string', 'max:100'],
        ])['archive_reason'];

        $nurse->update([
            'is_active' => false,
            'archived_at' => now(),
            'archived_by' => auth()->id(),
            'archive_reason' => $reason,
        ]);

        AuditLog::recordAction('staff_archived', 'Archived staff account '.$nurse->name, $nurse, ['reason' => $reason]);

        return to_route('nurses.index')->with('status', 'Account archived.');
    }

    private function authorizeManagedUser(User $user): void
    {
        $manager = auth()->user();

        if ($manager->canManageBarangayAdmins()) {
            abort_unless($user->isBarangayAdmin(), 404);
            abort_unless($manager->isSuperAdmin() || $user->municipality_id === $manager->municipality_id, 403);

            return;
        }

        abort_unless($manager->canManageNurses(), 403);
        abort_unless($user->isNurse(), 404);
        abort_if($user->isBarangayAdmin(), 404);
        abort_if($manager->isMunicipalAdmin()
            ? $user->municipality_id !== $manager->municipality_id
            : $user->barangay_id !== $manager->barangay_id, 403);
    }

    private function authorizeStaffManager(): void
    {
        abort_unless(auth()->user()->canManageBarangayStaff(), 403);
    }

    private function setupLinkResponse(User $user, string $statusMessage, bool $forceSetupLink = false): RedirectResponse
    {
        $response = to_route('nurses.index')->with('status', $statusMessage);

        if (! $forceSetupLink && ! in_array(config('mail.default'), ['log', 'array'], true)) {
            return $response;
        }

        $token = Password::broker()->createToken($user);

        $route = $user->invitation_accepted_at === null ? 'password.create' : 'password.reset';

        return $response->with('setup_link', route($route, [
            'token' => $token,
            'email' => $user->email,
        ]));
    }

    private function usesOfflineFacilitySetup(): bool
    {
        return config('system.instance_type') === 'facility'
            && (bool) config('offline.enabled');
    }
}
