<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use App\Services\FacilityActivationService;
use App\Services\OfflineSyncService;
use App\Services\FacilityPushSyncService;
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

        $barangay = $this->barangayForInstallation($installation);

        abort_unless(filled($installation->setup_user_email), 422, 'No designated Barangay Admin is assigned to this facility. Contact the Central administrator.');

        if ($this->hasBarangayAdmin($barangay)) {
            return to_route('home');
        }

        return view('facility.setup', compact('installation', 'barangay'));
    }

    public function store(Request $request, FacilityActivationService $activation): RedirectResponse
    {
        $installation = $activation->localInstallation();
        abort_unless($installation->status === 'active', 404);

        $barangay = $this->barangayForInstallation($installation);

        if ($this->hasBarangayAdmin($barangay)) {
            return to_route('home');
        }

        abort_unless(filled($installation->setup_user_email), 422, 'No designated Barangay Admin is assigned to this facility. Contact the Central administrator.');
        $data = $request->validate(['password' => ['required', 'string', Password::default(), 'confirmed']]);

        DB::transaction(function () use ($data, $barangay, $installation): void {
            if ($this->hasBarangayAdmin($barangay)) {
                return;
            }

            User::create([
                'name' => $installation->setup_user_name,
                'email' => $installation->setup_user_email,
                'password' => $data['password'],
                'role' => 'barangay_admin',
                'roles' => ['barangay_admin'],
                'barangay_id' => $barangay->id,
                'municipality_id' => $barangay->municipality_id,
                'is_active' => true,
                'invitation_accepted_at' => now(),
            ]);
            app(OfflineSyncService::class)->queueStaff(User::query()->where('email', $installation->setup_user_email)->firstOrFail());
        });

        // Try to update Central immediately when the connection is available.
        // The account is already saved locally, so an offline failure must not
        // prevent the user from signing in; the queued event can be synced later.
        try {
            app(FacilityPushSyncService::class)->synchronize();
        } catch (\Throwable $exception) {
            report($exception);
        }

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

    private function barangayForInstallation(\App\Models\SystemInstallation $installation): Barangay
    {
        $barangay = $installation->barangay_id ? Barangay::find($installation->barangay_id) : null;

        abort_unless($barangay, 422, 'This facility has no assigned Barangay. Contact the Central administrator.');

        return $barangay;
    }
}
