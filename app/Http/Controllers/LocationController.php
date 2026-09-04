<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LocationController extends Controller
{
    public function region(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:regions,name'], 'code' => ['nullable', 'string', 'max:30', 'unique:regions,code']]);
        Region::create($data);

        return back()->with('status', 'Region added.');
    }

    public function province(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $data = $request->validate(['region_id' => ['required', 'exists:regions,id'], 'name' => ['required', 'string', 'max:255'], 'code' => ['nullable', 'string', 'max:30', 'unique:provinces,code']]);
        Province::create($data);

        return back()->with('status', 'Province added.');
    }

    public function municipality(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $data = $request->validate(['province_id' => ['required', 'exists:provinces,id'], 'name' => ['required', 'string', 'max:255'], 'code' => ['nullable', 'string', 'max:30', 'unique:municipalities,code']]);
        Municipality::create($data);

        return back()->with('status', 'Municipality added.');
    }

    public function barangay(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $data = $request->validate(['municipality_id' => ['required', 'exists:municipalities,id'], 'name' => ['required', 'string', 'max:255']]);
        Barangay::create($data);

        return back()->with('status', 'Barangay added.');
    }

    public function assignMunicipality(Request $request, Municipality $municipality): RedirectResponse
    {
        $this->authorizeAdmin();
        $data = $request->validate(['email' => ['required', 'email', 'exists:users,email']]);
        User::where('email', $data['email'])->update(['municipality_id' => $municipality->id, 'barangay_id' => null]);

        return back()->with('status', 'User assigned to municipality.');
    }

    public function assignBarangay(Request $request, Barangay $barangay): RedirectResponse
    {
        $this->authorizeAdmin();
        $data = $request->validate(['email' => ['required', 'email', 'exists:users,email']]);
        User::where('email', $data['email'])->update(['municipality_id' => $barangay->municipality_id, 'barangay_id' => $barangay->id]);

        return back()->with('status', 'User assigned to barangay.');
    }

    public function addToMunicipality(Request $request, Municipality $municipality): RedirectResponse
    {
        return $this->addUser($request, $municipality->id, null, $municipality->id);
    }

    public function addToBarangay(Request $request, Barangay $barangay): RedirectResponse
    {
        return $this->addUser($request, $barangay->municipality_id, $barangay->id, $barangay->id);
    }

    public function removeUser(User $user): RedirectResponse
    {
        $this->authorizeAdmin();
        $user->update(['municipality_id' => null, 'barangay_id' => null]);

        return back()->with('status', 'User removed from the location.');
    }

    public function reassignUser(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin();
        $data = $request->validate(['municipality_id' => ['nullable', 'exists:municipalities,id'], 'barangay_id' => ['nullable', 'exists:barangays,id']]);
        if (filled($data['barangay_id'] ?? null)) {
            $data['municipality_id'] = Barangay::whereKey($data['barangay_id'])->value('municipality_id');
        }
        abort_if(blank($data['municipality_id'] ?? null), 422, 'Select a municipality or barangay.');
        $user->update(['municipality_id' => $data['municipality_id'], 'barangay_id' => $data['barangay_id'] ?? null]);

        return back()->with('status', 'User reassigned.');
    }

    private function addUser(Request $request, string $municipalityId, ?string $barangayId, string $locationId): RedirectResponse
    {
        $this->authorizeAdmin();
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'role' => ['required', 'in:barangay_admin,nurse,midwife,bhw,inventory_staff,municipal_admin']]);
        $user = User::firstOrNew(['email' => $data['email']]);
        $user->fill(['name' => $data['name'], 'password' => $user->exists ? $user->password : Str::password(32), 'role' => $data['role'], 'roles' => [$data['role']], 'municipality_id' => $municipalityId, 'barangay_id' => $barangayId, 'is_active' => $user->exists ? $user->is_active : false]);
        $user->save();

        return back()->with('status', 'User added to the location and marked pending until account setup.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }
}
