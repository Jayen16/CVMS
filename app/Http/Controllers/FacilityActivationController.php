<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Services\FacilityActivationService;
use App\Services\FacilitySyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FacilityActivationController extends Controller
{
    public function show(FacilityActivationService $activation): View
    {
        return view('facility.activate', ['installation' => $activation->localInstallation()]);
    }

    public function activate(Request $request, FacilityActivationService $activation, FacilitySyncService $sync): RedirectResponse
    {
        $data = $request->validate(['central_url' => ['required', 'url'], 'activation_code' => ['required', 'string', 'size:32']]);

        try {
            $activation->activateLocal($data['central_url'], $data['activation_code']);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['activation_code' => 'Activation failed. Check the central URL and activation code.'])->withInput();
        }

        try {
            $sync->synchronize();
        } catch (\Throwable $exception) {
            report($exception);

            return to_route('home')->with('status', 'Facility connected. Initial synchronization is pending; use Sync now when Central is reachable.');
        }

        return to_route('home')->with('status', 'Facility connected and initial data synchronized.');
    }

    public function facilities(Request $request): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        return view('central.facilities', ['facilities' => Facility::query()->withCount([
            'connections as active_connections_count' => fn ($query) => $query->where('status', 'active'),
            'activationCodes',
        ])->latest()->get()]);
    }

    public function storeFacility(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        Facility::create($request->validate(['code' => ['required', 'string', 'max:50', 'unique:facilities,code'], 'name' => ['required', 'string', 'max:255']]) + ['active' => true]);

        return back()->with('status', 'Facility registered.');
    }

    public function issueCode(Facility $facility, FacilityActivationService $activation): RedirectResponse
    {
        abort_unless(request()->user()->isSuperAdmin(), 403);

        session()->put([
            'activation_code' => $activation->issueCode($facility),
            'activation_code_facility_id' => $facility->id,
            'activation_code_expires_at' => now()->addHours(config('system.activation_code_ttl_hours', 24))->toIso8601String(),
        ]);
        session()->flash('activation_code_notice', true);

        return back();
    }

    public function revokeConnections(Request $request, Facility $facility, FacilityActivationService $activation): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $count = $activation->revokeFacilityConnections($facility);

        return back()->with('status', $count === 1
            ? 'The facility connection was revoked.'
            : "{$count} facility connections were revoked.");
    }

    public function activateApi(Request $request, FacilityActivationService $activation): JsonResponse
    {
        $data = $request->validate(['activation_code' => ['required', 'string', 'size:32'], 'instance_uuid' => ['required', 'uuid'], 'instance_name' => ['nullable', 'string', 'max:255']]);

        return response()->json(['data' => $activation->activateCentral($data['activation_code'], $data['instance_uuid'], $data['instance_name'] ?? 'Facility installation')]);
    }
}
