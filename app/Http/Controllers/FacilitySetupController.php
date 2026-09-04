<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use App\Services\FacilityActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Validation\Rules\Password;

class FacilitySetupController extends Controller
{
    public function show(FacilityActivationService $activation): View|RedirectResponse
    {
        $installation = $activation->localInstallation();
        abort_unless($installation->status === 'active', 404);

        $barangay = $this->barangayForInstallation($installation->facility_name);

        if ($this->hasBarangayAdmin($barangay)) {
            return to_route('home');
        }

        return view('facility.setup', compact('installation', 'barangay'));
    }

    public function store(Request $request, FacilityActivationService $activation): RedirectResponse
    {
        $installation = $activation->localInstallation();
        abort_unless($installation->status === 'active', 404);

        $barangay = $this->barangayForInstallation($installation->facility_name);

        if ($this->hasBarangayAdmin($barangay)) {
            return to_route('home');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32', 'unique:users,phone'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        DB::transaction(function () use ($data, $barangay): void {
            if ($this->hasBarangayAdmin($barangay)) {
                return;
            }

            User::create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => User::normalizePhone($data['phone'] ?? null),
                'password' => $data['password'],
                'role' => 'barangay_admin',
                'roles' => ['barangay_admin'],
                'barangay_id' => $barangay->id,
                'municipality_id' => $barangay->municipality_id,
                'is_active' => true,
                'invitation_accepted_at' => now(),
            ]);
        });

        return to_route('home')->with('status', 'Barangay administrator account created. You can now sign in.');
    }

    private function hasBarangayAdmin(Barangay $barangay): bool
    {
        return User::query()
            ->where('barangay_id', $barangay->id)
            ->where(function ($query): void {
                $query->where('role', 'barangay_admin')->orWhereJsonContains('roles', 'barangay_admin');
            })
            ->notArchived()
            ->exists();
    }

    private function barangayForInstallation(?string $facilityName): Barangay
    {
        $name = trim((string) $facilityName);
        $barangay = Barangay::query()->where('name', $name)->first();

        if ($barangay === null && str_starts_with(strtolower($name), 'barangay ')) {
            $barangay = Barangay::query()->where('name', trim(substr($name, 9)))->first();
        }

        return $barangay ?? Barangay::query()->create(['name' => $name]);
    }
}
